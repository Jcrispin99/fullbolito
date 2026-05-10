<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Journal;
use App\Models\Movement;
use App\Models\Transfer;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Orquestador del flujo de Transferencias entre almacenes.
 *
 * Una Transfer es un *header* que agrupa dos Movements:
 *   - exit  (en el almacén origen)
 *   - entry (en el almacén destino)
 *
 * Cada movement tiene su propio ciclo de aprobación gestionado por
 * MovementService. TransferService crea el header + las dos líneas
 * en draft, y delega las transiciones al MovementService.
 *
 * Los métodos `send` / `receive` / `cancel` mantienen la API histórica
 * del controlador como atajos: `send` = submit+post del exit,
 * `receive` = submit+post del entry, `cancel` cancela ambos en orden inverso.
 */
final class TransferService
{
    public function __construct(
        private readonly MovementService $movementService,
        private readonly KardexService $kardex,
    ) {}

    /**
     * Crea un Transfer (header) más sus dos Movements en `draft`. Las líneas
     * (productables) se duplican en cada movement con el costo resuelto.
     *
     * @param  array{
     *   company_id: int,
     *   from_warehouse_id: int,
     *   to_warehouse_id: int,
     *   observation?: string|null,
     *   products: array<int, array{product_product_id: int, quantity: float|int|string, lot_id?: int|null}>
     * }  $data
     */
    public function create(array $data, int $userId): Transfer
    {
        return DB::transaction(function () use ($data, $userId) {
            $companyId = (int) $data['company_id'];

            $fromWarehouse = Warehouse::findOrFail($data['from_warehouse_id']);
            $toWarehouse = Warehouse::findOrFail($data['to_warehouse_id']);

            if ((int) $fromWarehouse->id === (int) $toWarehouse->id) {
                throw new RuntimeException('El almacén origen y destino deben ser distintos.');
            }

            // Header
            [$serie, $correlative] = $this->nextSequenceForType('transfer', $companyId);

            $transfer = Transfer::create([
                'serie' => $serie,
                'correlative' => $correlative,
                'date' => now(),
                'company_id' => $companyId,
                'created_user_id' => $userId,
                'observation' => $data['observation'] ?? null,
                'total' => 0,
            ]);

            // Movements (draft)
            $exit = $this->createMovementShell(
                'exit',
                'transfer_exit',
                (int) $fromWarehouse->id,
                $companyId,
                $transfer->id,
                $userId,
            );

            $entry = $this->createMovementShell(
                'entry',
                'transfer_entry',
                (int) $toWarehouse->id,
                $companyId,
                $transfer->id,
                $userId,
            );

            // Líneas: duplicadas en exit y entry. El costo se resuelve desde el
            // origen (lote o costo promedio del producto en el almacén origen).
            $total = 0.0;

            foreach ($data['products'] as $row) {
                $qty = (float) $row['quantity'];
                $lotId = isset($row['lot_id']) ? (int) $row['lot_id'] : null;
                $productId = (int) $row['product_product_id'];

                $unitCost = $this->resolveCostFromOrigin($productId, (int) $fromWarehouse->id, $lotId);
                $lineTotal = $qty * $unitCost;

                $exit->productables()->create([
                    'product_product_id' => $productId,
                    'lot_id' => $lotId,
                    'quantity' => $qty,
                    'price' => $unitCost,
                    'subtotal' => $lineTotal,
                    'tax_rate' => 0,
                    'tax_amount' => 0,
                    'total' => $lineTotal,
                ]);

                $entry->productables()->create([
                    'product_product_id' => $productId,
                    'lot_id' => $lotId,
                    'quantity' => $qty,
                    'price' => $unitCost,
                    'subtotal' => $lineTotal,
                    'tax_rate' => 0,
                    'tax_amount' => 0,
                    'total' => $lineTotal,
                ]);

                $total += $lineTotal;
            }

            // Totales sincronizados
            $exit->update(['total' => $total]);
            $entry->update(['total' => $total]);
            $transfer->update(['total' => $total]);

            return $transfer->refresh();
        });
    }

    /**
     * Reemplaza líneas y/o almacenes mientras la transferencia esté en estado
     * `draft` (ambos movements en draft). Lanza si ya cualquiera fue movido
     * de borrador.
     *
     * @param  array{
     *   from_warehouse_id?: int,
     *   to_warehouse_id?: int,
     *   observation?: string|null,
     *   products?: array<int, array{product_product_id: int, quantity: float|int|string, lot_id?: int|null}>
     * }  $data
     */
    public function update(Transfer $transfer, array $data): Transfer
    {
        $transfer->loadMissing(['exitMovement', 'entryMovement']);
        /** @var Movement|null $exit */
        $exit = $transfer->exitMovement;
        /** @var Movement|null $entry */
        $entry = $transfer->entryMovement;

        if (! $exit || ! $entry) {
            throw new RuntimeException('Transferencia inconsistente: faltan movements pareados.');
        }

        if (! $exit->isDraft() || ! $entry->isDraft()) {
            throw new RuntimeException('Sólo se puede editar una transferencia en borrador.');
        }

        return DB::transaction(function () use ($transfer, $exit, $entry, $data) {
            if (array_key_exists('from_warehouse_id', $data) && $data['from_warehouse_id']) {
                $exit->update(['warehouse_id' => (int) $data['from_warehouse_id']]);
            }

            if (array_key_exists('to_warehouse_id', $data) && $data['to_warehouse_id']) {
                $entry->update(['warehouse_id' => (int) $data['to_warehouse_id']]);
            }

            if (array_key_exists('observation', $data)) {
                $transfer->update(['observation' => $data['observation']]);
            }

            if (isset($data['products'])) {
                $exit->productables()->delete();
                $entry->productables()->delete();

                $fromWarehouseId = (int) $exit->warehouse_id;
                $total = 0.0;

                foreach ($data['products'] as $row) {
                    $qty = (float) $row['quantity'];
                    $lotId = isset($row['lot_id']) ? (int) $row['lot_id'] : null;
                    $productId = (int) $row['product_product_id'];

                    $unitCost = $this->resolveCostFromOrigin($productId, $fromWarehouseId, $lotId);
                    $lineTotal = $qty * $unitCost;

                    $exit->productables()->create([
                        'product_product_id' => $productId,
                        'lot_id' => $lotId,
                        'quantity' => $qty,
                        'price' => $unitCost,
                        'subtotal' => $lineTotal,
                        'tax_rate' => 0,
                        'tax_amount' => 0,
                        'total' => $lineTotal,
                    ]);

                    $entry->productables()->create([
                        'product_product_id' => $productId,
                        'lot_id' => $lotId,
                        'quantity' => $qty,
                        'price' => $unitCost,
                        'subtotal' => $lineTotal,
                        'tax_rate' => 0,
                        'tax_amount' => 0,
                        'total' => $lineTotal,
                    ]);

                    $total += $lineTotal;
                }

                $exit->update(['total' => $total]);
                $entry->update(['total' => $total]);
                $transfer->update(['total' => $total]);
            }

            return $transfer->refresh();
        });
    }

    /**
     * Atajo histórico: registra la salida del almacén origen.
     * Equivale a submit + post del exit movement.
     */
    public function send(Transfer $transfer, int $userId): Transfer
    {
        $transfer->loadMissing('exitMovement');
        /** @var Movement|null $exit */
        $exit = $transfer->exitMovement;

        if (! $exit) {
            throw new RuntimeException('Transferencia sin movement de salida.');
        }

        if ($exit->isDraft()) {
            $this->movementService->submit($exit, $userId);
            $exit->refresh();
        }

        if (! $exit->isSubmitted()) {
            throw new RuntimeException('El movimiento de salida no está en estado válido para enviar.');
        }

        $this->movementService->post($exit, $userId);

        return $transfer->refresh();
    }

    /**
     * Atajo histórico: registra la entrada en el almacén destino.
     * Equivale a submit + post del entry movement.
     */
    public function receive(Transfer $transfer, int $userId): Transfer
    {
        $transfer->loadMissing('entryMovement');
        /** @var Movement|null $entry */
        $entry = $transfer->entryMovement;

        if (! $entry) {
            throw new RuntimeException('Transferencia sin movement de entrada.');
        }

        if ($entry->isDraft()) {
            $this->movementService->submit($entry, $userId);
            $entry->refresh();
        }

        if (! $entry->isSubmitted()) {
            throw new RuntimeException('El movimiento de entrada no está en estado válido para recibir.');
        }

        $this->movementService->post($entry, $userId);

        return $transfer->refresh();
    }

    /**
     * Cancela una transferencia revirtiendo los movements en orden inverso:
     *   1. Si entry está posted → cancelarlo (sale del destino).
     *   2. Si exit está posted → cancelarlo (regresa al origen).
     *   3. Si alguno está en draft/submitted/rejected → marcarlo cancelled
     *      sin contra-asiento (no impactó kardex).
     */
    public function cancel(Transfer $transfer, int $userId): Transfer
    {
        $transfer->loadMissing(['exitMovement', 'entryMovement']);
        /** @var Movement|null $exit */
        $exit = $transfer->exitMovement;
        /** @var Movement|null $entry */
        $entry = $transfer->entryMovement;

        if (! $exit || ! $entry) {
            throw new RuntimeException('Transferencia inconsistente: faltan movements pareados.');
        }

        return DB::transaction(function () use ($transfer, $exit, $entry, $userId) {
            // 1. entry primero (si está posted)
            if ($entry->isPosted()) {
                $this->movementService->cancel($entry, $userId);
            } elseif (! $entry->isCancelled()) {
                $entry->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'cancelled_user_id' => $userId,
                ]);
            }

            // 2. exit después
            if ($exit->isPosted()) {
                $this->movementService->cancel($exit, $userId);
            } elseif (! $exit->isCancelled()) {
                $exit->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'cancelled_user_id' => $userId,
                ]);
            }

            return $transfer->refresh();
        });
    }

    // ─── Helpers ────────────────────────────────────────────────────────────

    /**
     * Crea un Movement vacío (sin líneas) en draft, con su serie/correlativo
     * tomado del journal correspondiente al tipo.
     */
    private function createMovementShell(
        string $type,
        string $reason,
        int $warehouseId,
        int $companyId,
        int $transferId,
        int $userId,
    ): Movement {
        $journalType = $type === 'exit' ? 'movement_exit' : 'movement_entry';
        [$serie, $correlative] = $this->nextSequenceForType($journalType, $companyId);

        $journal = Journal::where('type', $journalType)
            ->where('company_id', $companyId)
            ->first();

        return Movement::create([
            'type' => $type,
            'serie' => $serie,
            'correlative' => $correlative,
            'date' => now(),
            'total' => 0,
            'reason' => $reason,
            'warehouse_id' => $warehouseId,
            'company_id' => $companyId,
            'journal_id' => $journal?->id,
            'transfer_id' => $transferId,
            'status' => 'draft',
            'created_user_id' => $userId,
        ]);
    }

    /**
     * Resuelve el costo unitario para una línea de transferencia desde el
     * almacén origen (lote → initial_cost; sin lote → costo promedio kardex).
     */
    private function resolveCostFromOrigin(int $productProductId, int $fromWarehouseId, ?int $lotId): float
    {
        if ($lotId !== null) {
            $lot = \App\Models\Lot::find($lotId);

            return (float) ($lot?->initial_cost ?? 0);
        }

        $last = $this->kardex->getLastRecord($productProductId, $fromWarehouseId);

        return (float) $last['cost'];
    }

    /**
     * Obtiene la siguiente serie/correlativo del journal del tipo dado para
     * la company indicada.
     *
     * @return array{0: string, 1: string}
     */
    private function nextSequenceForType(string $journalType, int $companyId): array
    {
        $journal = Journal::where('type', $journalType)
            ->where('company_id', $companyId)
            ->first();

        if (! $journal) {
            throw ValidationException::withMessages([
                'journal' => "No se encontró un diario de tipo '{$journalType}' para esta compañía. Ejecuta el JournalSeeder.",
            ]);
        }

        $parts = SequenceService::getNextParts($journal->id);

        return [$parts['serie'], $parts['correlative']];
    }
}
