<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Inventory;
use App\Models\Lot;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class SaleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'document_number' => $this->document_number,
            'serie' => $this->serie,
            'correlative' => $this->correlative,
            'date' => $this->date ? $this->date->format('Y-m-d H:i:s') : null,
            'notes' => $this->notes,
            'status' => $this->status,
            'payment_status' => $this->payment_status,

            // SUNAT (facturación electrónica)
            'sunat_status' => $this->sunat_status,
            'sunat_response' => $this->sunat_response,
            'sunat_sent_at' => $this->sunat_sent_at ? $this->sunat_sent_at->format('Y-m-d H:i:s') : null,
            // Flags de disponibilidad — la UI usa esto para mostrar botones de descarga.
            'has_signed_xml' => filled($this->signed_xml_path),
            'has_cdr_zip' => filled($this->cdr_zip_path),

            // Subtotals & Tax
            'subtotal' => (float) $this->subtotal,
            'tax_amount' => (float) $this->tax_amount,
            'total' => (float) $this->total,

            // Simple Relations
            'partner_id' => $this->partner_id,
            'warehouse_id' => $this->warehouse_id,
            'journal_id' => $this->journal_id,
            'company_id' => $this->company_id,
            'user_id' => $this->user_id,
            'seller_id' => $this->user_id,

            // Timestamps
            'created_at' => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
            'updated_at' => $this->updated_at ? $this->updated_at->format('Y-m-d H:i:s') : null,

            // Eager Loaded Relations
            'partner' => new CustomerResource($this->whenLoaded('partner')),
            'warehouse' => new WarehouseResource($this->whenLoaded('warehouse')),
            'company' => new CompanyResource($this->whenLoaded('company')),

            // Using the polymorphic ProductableResource
            'products' => ProductableResource::collection($this->whenLoaded('products')),

            // Nested relations via array mapped directly
            'journal' => $this->whenLoaded('journal', function () {
                return [
                    'id' => $this->journal->id,
                    'name' => $this->journal->name,
                    'code' => $this->journal->code,
                    'document_type_code' => $this->journal->document_type_code,
                ];
            }),

            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                ];
            }),

            'pos_session' => $this->whenLoaded('posSession', function () {
                return [
                    'id' => $this->posSession->id,
                    'opened_at' => $this->posSession->opened_at?->format('Y-m-d H:i:s'),
                    'pos_config_name' => $this->posSession->relationLoaded('posConfig')
                        ? $this->posSession->posConfig?->name
                        : null,
                ];
            }),
            'seller' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                ];
            }),

            'original_sale' => $this->whenLoaded('originalSale', function () {
                return [
                    'id' => $this->originalSale->id,
                    'document' => $this->originalSale->document_number,
                    'status' => $this->originalSale->status,
                    'journal_code' => $this->originalSale->journal ? $this->originalSale->journal->code : null,
                    'doc_type' => $this->originalSale->journal ? (string) $this->originalSale->journal->document_type_code : '',
                ];
            }),

            'credit_notes' => $this->whenLoaded('creditNotes', function () {
                return $this->creditNotes->map(function ($note) {
                    return [
                        'id' => $note->id,
                        'document' => $note->document_number,
                        'status' => $note->status,
                        'journal_code' => $note->journal ? $note->journal->code : null,
                        'doc_type' => $note->journal ? (string) $note->journal->document_type_code : '',
                    ];
                });
            }),

            // Resumen de devoluciones aplicadas a esta venta (solo para ventas
            // originales, no para NCs). Refleja únicamente NCs en estado posted.
            // Mirror lógico de SaleRefundService::computeAlreadyRefunded para que
            // la UI de devolución pueda calcular cuánto queda disponible por línea.
            'refunds_summary' => $this->when(
                $this->relationLoaded('creditNotes') && $this->relationLoaded('products') && $this->original_sale_id === null,
                function () {
                    $byPid = [];
                    $totalRefunded = 0.0;

                    foreach ($this->creditNotes as $note) {
                        if ($note->status !== 'posted') {
                            continue;
                        }
                        foreach ($note->products as $line) {
                            $pid = (int) $line->product_product_id;
                            if (! isset($byPid[$pid])) {
                                $byPid[$pid] = ['quantity' => 0.0, 'total' => 0.0];
                            }
                            $byPid[$pid]['quantity'] += (float) $line->quantity;
                            $byPid[$pid]['total'] += (float) $line->total;
                        }
                        $totalRefunded += (float) $note->total;
                    }

                    $originalByPid = [];
                    foreach ($this->products as $line) {
                        $pid = (int) $line->product_product_id;
                        if (! isset($originalByPid[$pid])) {
                            $originalByPid[$pid] = ['quantity' => 0.0, 'total' => 0.0];
                        }
                        $originalByPid[$pid]['quantity'] += (float) $line->quantity;
                        $originalByPid[$pid]['total'] += (float) $line->total;
                    }

                    $lines = [];
                    foreach ($originalByPid as $pid => $original) {
                        $refunded = $byPid[$pid] ?? ['quantity' => 0.0, 'total' => 0.0];
                        $lines[] = [
                            'product_product_id' => $pid,
                            'original_quantity' => round($original['quantity'], 4),
                            'refunded_quantity' => round($refunded['quantity'], 4),
                            'available_quantity' => round(
                                max(0, $original['quantity'] - $refunded['quantity']),
                                4,
                            ),
                            'original_total' => round($original['total'], 2),
                            'refunded_total' => round($refunded['total'], 2),
                            'available_total' => round(
                                max(0, $original['total'] - $refunded['total']),
                                2,
                            ),
                            'lots' => [],
                        ];
                    }

                    // Lot breakdown per line: lee del kardex (inventories) las salidas
                    // de la venta original y las entradas de las NCs posteadas, agrupadas
                    // por (product_product_id, lot_id). Solo aplica a lots no nulos.
                    $saleClass = (new Sale)->getMorphClass();
                    $creditNoteIds = $this->creditNotes
                        ->where('status', 'posted')
                        ->pluck('id')
                        ->all();

                    $exits = Inventory::query()
                        ->where('inventoryable_type', $saleClass)
                        ->where('inventoryable_id', $this->id)
                        ->where('quantity_out', '>', 0)
                        ->whereNotNull('lot_id')
                        ->selectRaw('product_product_id, lot_id, SUM(quantity_out) as qty')
                        ->groupBy('product_product_id', 'lot_id')
                        ->get();

                    $entries = collect();
                    if (! empty($creditNoteIds)) {
                        $entries = Inventory::query()
                            ->where('inventoryable_type', $saleClass)
                            ->whereIn('inventoryable_id', $creditNoteIds)
                            ->where('quantity_in', '>', 0)
                            ->whereNotNull('lot_id')
                            ->selectRaw('product_product_id, lot_id, SUM(quantity_in) as qty')
                            ->groupBy('product_product_id', 'lot_id')
                            ->get();
                    }

                    $lotStats = [];
                    foreach ($exits as $row) {
                        $pid = (int) $row->product_product_id;
                        $lid = (int) $row->lot_id;
                        $lotStats[$pid][$lid] = [
                            'sold' => (float) $row->qty,
                            'refunded' => 0.0,
                        ];
                    }
                    foreach ($entries as $row) {
                        $pid = (int) $row->product_product_id;
                        $lid = (int) $row->lot_id;
                        if (isset($lotStats[$pid][$lid])) {
                            $lotStats[$pid][$lid]['refunded'] = (float) $row->qty;
                        }
                    }

                    $allLotIds = [];
                    foreach ($lotStats as $byLot) {
                        foreach (array_keys($byLot) as $lid) {
                            $allLotIds[$lid] = true;
                        }
                    }
                    $lotsById = empty($allLotIds)
                        ? collect()
                        : Lot::whereIn('id', array_keys($allLotIds))->get()->keyBy('id');

                    foreach ($lines as &$line) {
                        $pid = $line['product_product_id'];
                        $byLot = $lotStats[$pid] ?? [];
                        foreach ($byLot as $lid => $stats) {
                            $lot = $lotsById->get($lid);
                            $line['lots'][] = [
                                'lot_id' => $lid,
                                'lot_number' => $lot?->lot_number,
                                'expires_at' => $lot?->expires_at?->format('Y-m-d'),
                                'sold_quantity' => round($stats['sold'], 4),
                                'refunded_quantity' => round($stats['refunded'], 4),
                                'available_quantity' => round(
                                    max(0, $stats['sold'] - $stats['refunded']),
                                    4,
                                ),
                            ];
                        }
                    }
                    unset($line);

                    return [
                        'lines' => $lines,
                        'totals' => [
                            'original_total' => round((float) $this->total, 2),
                            'refunded_total' => round($totalRefunded, 2),
                            'available_total' => round(
                                max(0, (float) $this->total - $totalRefunded),
                                2,
                            ),
                        ],
                    ];
                },
            ),

            'loyalty_transactions' => $this->whenLoaded('loyaltyTransactions', function () {
                return $this->loyaltyTransactions->map(function ($tx) {
                    return [
                        'id' => $tx->id,
                        'program_name' => $tx->program?->name,
                        'program_type' => $tx->program?->program_type,
                        'point_name' => $tx->program?->point_name ?? 'Puntos',
                        'type' => $tx->type,
                        'points' => $tx->points,
                        'balance' => $tx->balance,
                        'card_code' => $tx->card?->code,
                        'description' => $tx->description,
                    ];
                });
            }),
        ];
    }
}
