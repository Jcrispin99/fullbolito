<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Inventory;
use App\Models\Journal;
use App\Models\Lot;
use App\Models\PosSession;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Servicio compartido para emitir notas de crédito (devoluciones) sobre ventas.
 *
 * Reglas que enforcea:
 *  - La venta original debe estar publicada (status='posted') y con numeración.
 *  - No se puede emitir NC sobre otra NC (chequeo via original_sale_id).
 *  - La cantidad por línea no puede exceder lo vendido menos lo ya devuelto en NC previas postadas.
 *  - El total acumulado de NC posteadas no puede exceder el total original.
 *  - Precios e IGV se congelan del original (proporcionalmente a la cantidad devuelta).
 *  - El inventario regresa a los lotes originales (FEFO inverso). Remanente sin lote
 *    (vendido con stock negativo) reingresa al promedio ponderado actual.
 *  - Lealtad: se revierte vía LoyaltyService::reverseSalePoints en la primera NC.
 *    El reverso proporcional para devoluciones parciales sigue pendiente (TODO).
 */
final class SaleRefundService
{
    public function __construct(
        private readonly KardexService $kardexService,
        private readonly LoyaltyService $loyaltyService,
    ) {}

    /**
     * Calcula totales y cantidades ya devueltas (NC posteadas) para una venta original.
     *
     * @return array{by_line: array<int, array{quantity: float, total: float}>, totals: array{subtotal: float, tax_amount: float, total: float}}
     */
    public function computeAlreadyRefunded(Sale $original): array
    {
        $original->loadMissing('creditNotes.products');

        $byLine = [];
        $totals = ['subtotal' => 0.0, 'tax_amount' => 0.0, 'total' => 0.0];

        foreach ($original->creditNotes as $note) {
            if ($note->status !== 'posted') {
                continue;
            }

            foreach ($note->products as $line) {
                $pid = (int) $line->product_product_id;
                if (! isset($byLine[$pid])) {
                    $byLine[$pid] = ['quantity' => 0.0, 'total' => 0.0];
                }
                $byLine[$pid]['quantity'] += (float) $line->quantity;
                $byLine[$pid]['total'] += (float) $line->total;
            }

            $totals['subtotal'] += (float) $note->subtotal;
            $totals['tax_amount'] += (float) $note->tax_amount;
            $totals['total'] += (float) $note->total;
        }

        return ['by_line' => $byLine, 'totals' => $totals];
    }

    /**
     * Crea y postea una nota de crédito sobre $original con las líneas indicadas.
     *
     * @param  array{lines: array<int, array{product_product_id: int|string, quantity: int|float|string}>, notes?: ?string, pos_session_id?: ?int}  $payload
     */
    public function createFromOriginal(Sale $original, array $payload, ?User $user): Sale
    {
        return DB::transaction(function () use ($original, $payload, $user) {
            $this->assertOriginalEligible($original);

            $original->loadMissing(['products', 'creditNotes.products', 'journal']);

            $alreadyRefunded = $this->computeAlreadyRefunded($original);

            $refundLines = $this->prepareRefundLines(
                $original,
                $payload['lines'] ?? [],
                $alreadyRefunded['by_line'],
            );

            [$subtotal, $taxAmount, $total] = $this->sumRefundLines($refundLines);

            $remainingTotal = round((float) $original->total - $alreadyRefunded['totals']['total'], 2);
            if (round($total, 2) > $remainingTotal + 0.01) {
                throw ValidationException::withMessages([
                    'lines' => "El total a devolver ({$total}) excede el saldo disponible de la venta ({$remainingTotal}).",
                ]);
            }

            $journal = $this->resolveCreditNoteJournal($original, $payload['pos_session_id'] ?? null);
            [$serie, $correlative] = $this->consumeJournalSequence($journal);

            $note = Sale::create([
                'partner_id' => $original->partner_id,
                'warehouse_id' => $original->warehouse_id,
                'pos_session_id' => $payload['pos_session_id'] ?? null,
                'journal_id' => $journal->id,
                'company_id' => $original->company_id,
                'original_sale_id' => $original->id,
                'user_id' => $user?->id,
                'notes' => $payload['notes'] ?? "Nota de Crédito sobre {$original->serie}-{$original->correlative}",
                'status' => 'posted',
                'payment_status' => 'paid',
                'serie' => $serie,
                'correlative' => $correlative,
                'subtotal' => round($subtotal, 2),
                'tax_amount' => round($taxAmount, 2),
                'total' => round($total, 2),
            ]);

            foreach ($refundLines as $line) {
                $note->products()->create([
                    'product_product_id' => $line['product_product_id'],
                    'quantity' => $line['quantity'],
                    'price' => $line['price'],
                    'subtotal' => $line['subtotal'],
                    'tax_id' => $line['tax_id'],
                    'tax_rate' => $line['tax_rate'],
                    'tax_amount' => $line['tax_amount'],
                    'total' => $line['total'],
                    'uom_id' => $line['uom_id'],
                    'quantity_uom' => $line['quantity_uom'],
                    'price_uom' => $line['price_uom'],
                    'uom_factor' => $line['uom_factor'],
                ]);
            }

            $this->returnInventoryToOriginalLots($original, $note);

            $this->reverseLoyaltyIfApplicable($original);

            activity()
                ->performedOn($note)
                ->causedBy($user)
                ->withProperties(['original_sale_id' => $original->id])
                ->log('Nota de Crédito generada y posteada');

            return $note;
        });
    }

    private function assertOriginalEligible(Sale $original): void
    {
        if ($original->status !== 'posted') {
            throw ValidationException::withMessages([
                'sale_id' => 'Solo se pueden generar notas de crédito sobre ventas publicadas.',
            ]);
        }

        if (empty($original->serie) || empty($original->correlative)) {
            throw ValidationException::withMessages([
                'sale_id' => 'La venta original carece de numeración.',
            ]);
        }

        if ($original->original_sale_id !== null) {
            throw ValidationException::withMessages([
                'sale_id' => 'No se puede emitir una nota de crédito sobre otra nota de crédito.',
            ]);
        }
    }

    /**
     * Mapea líneas solicitadas contra las del original. Valida cantidades.
     * Calcula totales por línea proporcionalmente al original (precios congelados).
     *
     * @param  array<int, array{product_product_id: int|string, quantity: int|float|string}>  $requested
     * @param  array<int, array{quantity: float, total: float}>  $byLineRefunded
     * @return list<array<string, mixed>>
     */
    private function prepareRefundLines(Sale $original, array $requested, array $byLineRefunded): array
    {
        if (empty($requested)) {
            throw ValidationException::withMessages([
                'lines' => 'Debe especificar al menos una línea para devolver.',
            ]);
        }

        $originalByPid = [];
        foreach ($original->products as $line) {
            $pid = (int) $line->product_product_id;
            if (! isset($originalByPid[$pid])) {
                $originalByPid[$pid] = [
                    'quantity' => 0.0,
                    'price' => (float) $line->price,
                    'subtotal' => 0.0,
                    'tax_id' => $line->tax_id,
                    'tax_rate' => (float) $line->tax_rate,
                    'tax_amount' => 0.0,
                    'total' => 0.0,
                    'uom_id' => $line->uom_id,
                    'quantity_uom' => $line->quantity_uom !== null ? (float) $line->quantity_uom : null,
                    'price_uom' => $line->price_uom !== null ? (float) $line->price_uom : null,
                    'uom_factor' => (float) $line->uom_factor,
                ];
            }

            $originalByPid[$pid]['quantity'] += (float) $line->quantity;
            $originalByPid[$pid]['subtotal'] += (float) $line->subtotal;
            $originalByPid[$pid]['tax_amount'] += (float) $line->tax_amount;
            $originalByPid[$pid]['total'] += (float) $line->total;
        }

        $refundLines = [];
        foreach ($requested as $req) {
            $pid = (int) ($req['product_product_id'] ?? 0);
            $qty = (float) ($req['quantity'] ?? 0);

            if ($qty <= 0) {
                throw ValidationException::withMessages([
                    'lines' => "La cantidad debe ser mayor a 0 para el producto #{$pid}.",
                ]);
            }

            if (! isset($originalByPid[$pid])) {
                throw ValidationException::withMessages([
                    'lines' => "El producto #{$pid} no estaba en la venta original.",
                ]);
            }

            $originalQty = $originalByPid[$pid]['quantity'];
            $alreadyQty = (float) ($byLineRefunded[$pid]['quantity'] ?? 0);
            $available = round($originalQty - $alreadyQty, 6);

            if (round($qty, 6) > round($available, 6) + 0.0001) {
                throw ValidationException::withMessages([
                    'lines' => "Cantidad solicitada ({$qty}) excede disponible ({$available}) para el producto #{$pid}.",
                ]);
            }

            $proportion = $originalQty > 0 ? $qty / $originalQty : 0.0;

            $refundLines[] = [
                'product_product_id' => $pid,
                'quantity' => $qty,
                'price' => $originalByPid[$pid]['price'],
                'subtotal' => round($originalByPid[$pid]['subtotal'] * $proportion, 2),
                'tax_id' => $originalByPid[$pid]['tax_id'],
                'tax_rate' => $originalByPid[$pid]['tax_rate'],
                'tax_amount' => round($originalByPid[$pid]['tax_amount'] * $proportion, 2),
                'total' => round($originalByPid[$pid]['total'] * $proportion, 2),
                'uom_id' => $originalByPid[$pid]['uom_id'],
                'quantity_uom' => $originalByPid[$pid]['quantity_uom'] !== null
                    ? round($originalByPid[$pid]['quantity_uom'] * $proportion, 4)
                    : null,
                'price_uom' => $originalByPid[$pid]['price_uom'],
                'uom_factor' => $originalByPid[$pid]['uom_factor'],
            ];
        }

        return $refundLines;
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return array{0: float, 1: float, 2: float}
     */
    private function sumRefundLines(array $lines): array
    {
        $subtotal = 0.0;
        $taxAmount = 0.0;
        $total = 0.0;

        foreach ($lines as $line) {
            $subtotal += (float) $line['subtotal'];
            $taxAmount += (float) $line['tax_amount'];
            $total += (float) $line['total'];
        }

        return [$subtotal, $taxAmount, $total];
    }

    /**
     * Resuelve el journal de Nota de Crédito apropiado para la venta original.
     *
     * Estrategia (de más específica a más laxa):
     *  1. Journal asignado al PosConfig con `pivot.document_type='credit_note'`
     *     que afecte el mismo `document_type_code` que la venta original.
     *  2. Journal asignado al PosConfig con `pivot.document_type='credit_note'`
     *     sin filtro de affects_document_type_code (compat para configs viejas).
     *  3. Journal global de la compañía con `document_type_code='07'` cuyo
     *     `affects_document_type_code` coincida con la venta original.
     *  4. Journal global de la compañía con `document_type_code='07'` (cualquiera).
     *
     * Si nada coincide, error.
     */
    private function resolveCreditNoteJournal(Sale $original, ?int $posSessionId): Journal
    {
        $originalDocCode = $original->journal?->document_type_code;

        if ($posSessionId) {
            $session = PosSession::with('posConfig.journals')->find($posSessionId);
            $candidates = $session?->posConfig?->journals
                ->filter(fn (Journal $j) => $j->pivot?->document_type === 'credit_note');

            if ($candidates && $candidates->isNotEmpty()) {
                if ($originalDocCode) {
                    $matched = $candidates->first(
                        fn (Journal $j) => $j->affects_document_type_code === $originalDocCode,
                    );
                    if ($matched) {
                        return $matched;
                    }
                }

                return $candidates->first();
            }
        }

        $companyQuery = Journal::where('company_id', $original->company_id)
            ->where(function ($q) {
                $q->where('type', 'sale_refund')
                    ->orWhere('document_type_code', '07');
            });

        if ($originalDocCode) {
            $matched = (clone $companyQuery)
                ->where('affects_document_type_code', $originalDocCode)
                ->first();
            if ($matched) {
                return $matched;
            }
        }

        $journal = $companyQuery->first();

        if (! $journal) {
            throw ValidationException::withMessages([
                'journal' => 'No se encontró un diario de Nota de Crédito configurado para esta compañía.',
            ]);
        }

        return $journal;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function consumeJournalSequence(Journal $journal): array
    {
        $serie = $journal->code ?: 'FC01';
        $correlative = '0000001';

        if ($journal->sequence) {
            $serie = $journal->code;
            $correlative = str_pad(
                (string) $journal->sequence->next_number,
                $journal->sequence->sequence_size,
                '0',
                STR_PAD_LEFT,
            );
            $journal->sequence->increment('next_number', $journal->sequence->step);
        }

        return [$serie, $correlative];
    }

    /**
     * Devuelve el inventario al(los) lote(s) original(es) según las salidas del kardex
     * de la venta original, descontando lo ya devuelto en NCs previas posteadas.
     */
    private function returnInventoryToOriginalLots(Sale $original, Sale $note): void
    {
        $note->load('products.productProduct.template');

        foreach ($note->products as $productable) {
            $tracksInventory = $productable->productProduct?->template?->tracks_inventory ?? true;
            if (! $tracksInventory) {
                continue;
            }

            $pid = (int) $productable->product_product_id;
            $whId = (int) $note->warehouse_id;
            $remaining = (float) $productable->quantity;

            // Allocations originales (lote, cantidad) — acumuladas si la original tenía varias líneas
            $allocations = $this->kardexService->getExitAllocationsForModel($original, $pid, $whId);

            // Cantidades ya devueltas a cada lote por NCs previas posteadas
            $alreadyReturnedByLot = $this->computeAlreadyReturnedByLot($original, $pid, $whId, excludeNoteId: $note->id);

            foreach ($allocations as $alloc) {
                if ($remaining <= 0) {
                    break;
                }

                $lotId = (int) $alloc['lot_id'];
                $available = (float) $alloc['quantity'] - (float) ($alreadyReturnedByLot[$lotId] ?? 0);
                if ($available <= 0) {
                    continue;
                }

                $take = min($remaining, $available);
                $lot = Lot::find($lotId);
                $unitCost = (float) ($lot?->initial_cost ?? 0);

                $this->kardexService->registerEntry(
                    $note,
                    [
                        'id' => $pid,
                        'quantity' => $take,
                        'price' => $unitCost,
                        'subtotal' => $take * $unitCost,
                    ],
                    $whId,
                    "Devolución NC {$note->serie}-{$note->correlative}",
                    $lotId,
                );

                $remaining -= $take;
            }

            // Remanente sin lote (POS allowNegativeStock pudo escribir parte sin lote, o no había allocations)
            if ($remaining > 0) {
                $lastRecord = $this->kardexService->getLastRecord($pid, $whId);
                $currentCost = $lastRecord['cost'] ?? 0;

                $this->kardexService->registerEntry(
                    $note,
                    [
                        'id' => $pid,
                        'quantity' => $remaining,
                        'price' => $currentCost,
                        'subtotal' => $remaining * $currentCost,
                    ],
                    $whId,
                    "Devolución (sin lote) NC {$note->serie}-{$note->correlative}",
                );
            }
        }
    }

    /**
     * @return array<int, float>  Mapa lot_id => cantidad ya devuelta en NCs previas posteadas.
     */
    private function computeAlreadyReturnedByLot(Sale $original, int $productProductId, int $warehouseId, int $excludeNoteId): array
    {
        $original->loadMissing('creditNotes');

        $noteIds = $original->creditNotes
            ->filter(fn (Sale $n) => $n->status === 'posted' && $n->id !== $excludeNoteId)
            ->pluck('id')
            ->all();

        if (empty($noteIds)) {
            return [];
        }

        $rows = Inventory::query()
            ->whereIn('inventoryable_id', $noteIds)
            ->where('inventoryable_type', (new Sale)->getMorphClass())
            ->where('product_product_id', $productProductId)
            ->where('warehouse_id', $warehouseId)
            ->where('quantity_in', '>', 0)
            ->whereNotNull('lot_id')
            ->selectRaw('lot_id, SUM(quantity_in) as qty')
            ->groupBy('lot_id')
            ->get();

        $byLot = [];
        foreach ($rows as $row) {
            $byLot[(int) $row->lot_id] = (float) $row->qty;
        }

        return $byLot;
    }

    /**
     * Reverso de lealtad. Llama al servicio existente que es idempotente:
     * en la primera NC se revierten todos los puntos; en NCs subsiguientes
     * el guard interno (alreadyReversed) no hace nada.
     *
     * TODO: implementar reverso proporcional para devoluciones parciales,
     * de modo que el cliente pierda solo los puntos correspondientes a la
     * fracción devuelta en lugar de todos en la primera NC.
     */
    private function reverseLoyaltyIfApplicable(Sale $original): void
    {
        if (! $original->partner_id || ! $original->partner) {
            return;
        }

        $this->loyaltyService->reverseSalePoints($original, $original->partner);
    }
}
