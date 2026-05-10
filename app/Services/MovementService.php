<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Lot;
use App\Models\LotInventory;
use App\Models\Movement;
use App\Models\ProductProduct;
use App\Models\Productable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Maneja el ciclo de vida de un Movement (entry / exit) y su impacto en
 * el kardex. Cada transición es una operación atómica.
 *
 *   draft -> submitted -> posted    (camino feliz; impacta kardex)
 *                       -> rejected (vuelve a editar)
 *   posted -> cancelled              (escribe contra-asiento)
 *
 * El servicio NO crea Movements (eso lo hace el controller o
 * TransferService). Solo gestiona transiciones de estado.
 */
final class MovementService
{
    public function __construct(private readonly KardexService $kardex) {}

    /**
     * draft → submitted. Marca el movement como listo para aprobación.
     *
     * @throws RuntimeException
     */
    public function submit(Movement $movement, int $userId): Movement
    {
        if (! $movement->isDraft()) {
            throw new RuntimeException('Sólo se pueden enviar a aprobación movimientos en borrador.');
        }

        $movement->loadMissing('productables');

        if ($movement->productables->isEmpty()) {
            throw new RuntimeException('El movimiento debe tener al menos una línea.');
        }

        $this->assertLinesValid($movement);

        $movement->update([
            'status' => 'submitted',
            'submitted_at' => now(),
            'submitted_user_id' => $userId,
        ]);

        return $movement->refresh();
    }

    /**
     * submitted → posted. Aplica el movimiento al kardex.
     *
     * - Si es `exit`: descuenta del almacén (FEFO o lote explícito por línea).
     * - Si es `entry`: suma al almacén (vinculando al lote si la línea lo trae).
     *
     * @throws RuntimeException
     */
    public function post(Movement $movement, int $userId): Movement
    {
        if (! $movement->isSubmitted()) {
            throw new RuntimeException('Sólo se pueden aprobar movimientos enviados a aprobación.');
        }

        if ($movement->isExit()) {
            $this->assertStockAvailable($movement);
        }

        return DB::transaction(function () use ($movement, $userId) {
            $movement->loadMissing('productables');

            $detail = $this->buildDetail($movement);

            foreach ($movement->productables as $line) {
                /** @var Productable $line */
                if ($movement->isExit()) {
                    $this->postExitLine($movement, $line, $detail);
                } else {
                    $this->postEntryLine($movement, $line, $detail);
                }
            }

            $movement->update([
                'status' => 'posted',
                'posted_at' => now(),
                'posted_user_id' => $userId,
            ]);

            return $movement->refresh();
        });
    }

    /**
     * submitted → rejected. El aprobador devuelve el movimiento al solicitante
     * con una razón. No impacta kardex. Posteriormente puede re-enviarse:
     * el creador pasa de rejected → draft (vía controller) y vuelve a submitir.
     *
     * @throws RuntimeException
     */
    public function reject(Movement $movement, int $userId, ?string $reason = null): Movement
    {
        if (! $movement->isSubmitted()) {
            throw new RuntimeException('Sólo se pueden rechazar movimientos enviados a aprobación.');
        }

        $movement->update([
            'status' => 'rejected',
            'rejected_at' => now(),
            'rejected_user_id' => $userId,
            'rejection_reason' => $reason,
        ]);

        return $movement->refresh();
    }

    /**
     * posted → cancelled. Escribe contra-asiento al kardex (entry ↔ exit
     * invertido) para mantener el ledger append-only.
     *
     * @throws RuntimeException
     */
    public function cancel(Movement $movement, int $userId): Movement
    {
        if (! $movement->isPosted()) {
            throw new RuntimeException('Sólo se pueden cancelar movimientos aprobados (posted).');
        }

        return DB::transaction(function () use ($movement, $userId) {
            $movement->loadMissing('productables');

            $detail = 'Cancelación '.$this->buildDetail($movement);

            foreach ($movement->productables as $line) {
                /** @var Productable $line */
                if ($movement->isExit()) {
                    // Cancelar un exit = devolver stock (entry inverso)
                    $this->kardex->registerEntry(
                        $movement,
                        [
                            'id' => (int) $line->product_product_id,
                            'quantity' => (float) $line->quantity,
                            'price' => (float) $line->price,
                            'subtotal' => (float) $line->subtotal,
                        ],
                        (int) $movement->warehouse_id,
                        $detail,
                        $line->lot_id !== null ? (int) $line->lot_id : null,
                    );
                } else {
                    // Cancelar un entry = sacar stock (exit inverso)
                    $allocations = $line->lot_id !== null
                        ? [['lot_id' => (int) $line->lot_id, 'quantity' => (float) $line->quantity]]
                        : null;

                    $this->kardex->registerExit(
                        $movement,
                        [
                            'id' => (int) $line->product_product_id,
                            'quantity' => (float) $line->quantity,
                        ],
                        (int) $movement->warehouse_id,
                        $detail,
                        $allocations,
                    );
                }
            }

            $movement->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancelled_user_id' => $userId,
            ]);

            return $movement->refresh();
        });
    }

    /**
     * rejected → draft. Permite al creador reabrir un rechazado para editarlo
     * y volver a enviarlo a aprobación.
     *
     * @throws RuntimeException
     */
    public function reopen(Movement $movement): Movement
    {
        if (! $movement->isRejected()) {
            throw new RuntimeException('Sólo se pueden reabrir movimientos rechazados.');
        }

        $movement->update([
            'status' => 'draft',
            'submitted_at' => null,
            'submitted_user_id' => null,
            'rejected_at' => null,
            'rejected_user_id' => null,
            'rejection_reason' => null,
        ]);

        return $movement->refresh();
    }

    // ─── Validaciones ───────────────────────────────────────────────────────

    /**
     * Verifica que cada línea con producto trackeado tenga `lot_id`,
     * y que el lote pertenezca al producto y no esté bloqueado.
     *
     * @throws RuntimeException
     */
    public function assertLinesValid(Movement $movement): void
    {
        $movement->loadMissing('productables.productProduct.template', 'productables.lot');

        foreach ($movement->productables as $line) {
            /** @var Productable $line */
            /** @var ProductProduct|null $product */
            $product = $line->productProduct;
            $tracked = (bool) ($product?->template?->tracked_by_lot ?? false);

            // Para exits con producto trazado, el lote es obligatorio.
            // Para entries con producto trazado, el lote es obligatorio en el
            // momento del post (debe haberse creado antes desde el controller).
            if ($tracked && $line->lot_id === null) {
                $name = $product?->template?->name ?? "Producto #{$line->product_product_id}";
                throw new RuntimeException("El producto '{$name}' requiere seleccionar un lote.");
            }

            if ($line->lot_id !== null) {
                /** @var Lot|null $lot */
                $lot = $line->lot;

                if ($lot === null || (int) $lot->product_product_id !== (int) $line->product_product_id) {
                    throw new RuntimeException("El lote asignado a la línea {$line->id} no corresponde al producto.");
                }

                if ($lot->status === 'blocked') {
                    throw new RuntimeException("El lote {$lot->lot_number} está bloqueado.");
                }
            }
        }
    }

    /**
     * Verifica stock suficiente en el almacén del movement (sólo para `exit`).
     *
     * @throws RuntimeException
     */
    public function assertStockAvailable(Movement $movement): void
    {
        if (! $movement->isExit()) {
            return;
        }

        $movement->loadMissing('productables.productProduct.template');
        $warehouseId = (int) $movement->warehouse_id;

        foreach ($movement->productables as $line) {
            /** @var Productable $line */
            $qty = (float) $line->quantity;

            if ($line->lot_id !== null) {
                $li = LotInventory::where('lot_id', $line->lot_id)
                    ->where('warehouse_id', $warehouseId)
                    ->first();

                $stock = (float) ($li?->quantity_balance ?? 0);

                if ($stock + 0.0001 < $qty) {
                    throw new RuntimeException(
                        "Stock insuficiente en el lote (almacén origen). Disponible: {$stock}, requerido: {$qty}."
                    );
                }

                continue;
            }

            if (! $this->kardex->hasEnoughStock((int) $line->product_product_id, $warehouseId, $qty)) {
                $available = $this->kardex->getCurrentStock((int) $line->product_product_id, $warehouseId);
                throw new RuntimeException(
                    "Stock insuficiente en el almacén. Disponible: {$available}, requerido: {$qty}."
                );
            }
        }
    }

    // ─── Helpers ────────────────────────────────────────────────────────────

    /**
     * Costo unitario para una línea:
     * - Si tiene lote → `initial_cost` del lote.
     * - Sin lote (productos no trazados) → costo promedio actual del producto en el almacén.
     */
    public function resolveLineCost(Productable $line, int $warehouseId): float
    {
        if ($line->lot_id !== null) {
            $lot = Lot::find($line->lot_id);

            return (float) ($lot?->initial_cost ?? 0);
        }

        $last = $this->kardex->getLastRecord((int) $line->product_product_id, $warehouseId);

        return (float) $last['cost'];
    }

    // ─── Privados ───────────────────────────────────────────────────────────

    private function buildDetail(Movement $movement): string
    {
        if ($movement->isPartOfTransfer()) {
            $movement->loadMissing('transfer');
            $transfer = $movement->transfer;
            $head = $transfer ? "Transferencia {$transfer->serie}-{$transfer->correlative}" : 'Transferencia';
            $tail = $movement->isExit() ? 'envío' : 'recepción';

            return "{$head} ({$tail})";
        }

        $kind = $movement->isEntry() ? 'Entrada' : 'Salida';
        $reason = $movement->reason ? " - {$movement->reason}" : '';

        return "{$kind} {$movement->serie}-{$movement->correlative}{$reason}";
    }

    private function postExitLine(Movement $movement, Productable $line, string $detail): void
    {
        $allocations = $line->lot_id !== null
            ? [['lot_id' => (int) $line->lot_id, 'quantity' => (float) $line->quantity]]
            : null;

        $this->kardex->registerExit(
            $movement,
            [
                'id' => (int) $line->product_product_id,
                'quantity' => (float) $line->quantity,
            ],
            (int) $movement->warehouse_id,
            $detail,
            $allocations,
        );
    }

    private function postEntryLine(Movement $movement, Productable $line, string $detail): void
    {
        $this->kardex->registerEntry(
            $movement,
            [
                'id' => (int) $line->product_product_id,
                'quantity' => (float) $line->quantity,
                'price' => (float) $line->price,
                'subtotal' => (float) $line->subtotal,
            ],
            (int) $movement->warehouse_id,
            $detail,
            $line->lot_id !== null ? (int) $line->lot_id : null,
        );
    }
}
