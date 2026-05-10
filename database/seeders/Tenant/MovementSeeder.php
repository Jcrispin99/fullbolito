<?php

declare(strict_types=1);

namespace Database\Seeders\Tenant;

use App\Models\Company;
use App\Models\Journal;
use App\Models\Lot;
use App\Models\Movement;
use App\Models\ProductProduct;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\MovementService;
use App\Services\SequenceService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Crea Movements *sueltos* (no parte de una Transferencia) cubriendo todos
 * los estados del workflow: draft, submitted, posted, rejected, cancelled.
 *
 * Diseño:
 *   - Las entries usan productos NO trazados (no requieren lote).
 *   - El exit posted usa un lote existente del LotSeeder (con stock real).
 *   - Las transiciones se hacen vía MovementService para que el kardex y
 *     lot_inventories queden alineados con el comportamiento de runtime.
 */
final class MovementSeeder extends Seeder
{
    public function run(): void
    {
        if (Movement::query()->exists()) {
            return;
        }

        $company = Company::whereNull('parent_id')->first() ?? Company::first();
        $warehouse = Warehouse::orderBy('id')->first();
        $user = User::first();

        if (! $company || ! $warehouse || ! $user) {
            $this->command->error('Faltan datos base (Company / Warehouse / User). Ejecuta los seeders previos.');

            return;
        }

        $entryJournal = Journal::query()
            ->where('type', 'movement_entry')
            ->where('company_id', $company->id)
            ->first();
        $exitJournal = Journal::query()
            ->where('type', 'movement_exit')
            ->where('company_id', $company->id)
            ->first();

        if (! $entryJournal || ! $exitJournal) {
            $this->command->error('Faltan journals movement_entry / movement_exit. Ejecuta JournalSeeder.');

            return;
        }

        /** @var array<int, ProductProduct> $nonTracked */
        $nonTracked = ProductProduct::query()
            ->whereHas('template', fn ($q) => $q->where('tracked_by_lot', false)->where('is_active', true))
            ->orderBy('id')
            ->take(5)
            ->get()
            ->values()
            ->all();

        if (count($nonTracked) < 4) {
            $this->command->warn('No hay suficientes productos no-trazados para MovementSeeder (se requieren ≥4).');

            return;
        }

        // Alinear sequences contra registros existentes ANTES de crear nada,
        // para evitar colisiones si se mezclan seeder y creación runtime.
        $this->resyncSequences();

        /** @var MovementService $service */
        $service = app(MovementService::class);
        $userId = (int) $user->id;
        $companyId = (int) $company->id;
        $warehouseId = (int) $warehouse->id;

        $count = 0;

        // ── Entry helper ──────────────────────────────────────────────
        $createDraftEntry = function (
            ProductProduct $variant,
            float $qty,
            float $price,
            string $reason,
            int $daysAgo,
        ) use ($entryJournal, $warehouseId, $companyId, $userId): Movement {
            return DB::transaction(function () use (
                $entryJournal,
                $variant,
                $qty,
                $price,
                $reason,
                $warehouseId,
                $companyId,
                $userId,
                $daysAgo,
            ) {
                $parts = SequenceService::getNextParts($entryJournal->id);
                $lineTotal = round($qty * $price, 2);

                $movement = Movement::create([
                    'type' => 'entry',
                    'serie' => $parts['serie'],
                    'correlative' => $parts['correlative'],
                    'date' => now()->subDays($daysAgo),
                    'reason' => $reason,
                    'warehouse_id' => $warehouseId,
                    'company_id' => $companyId,
                    'journal_id' => $entryJournal->id,
                    'transfer_id' => null,
                    'status' => 'draft',
                    'total' => $lineTotal,
                    'created_user_id' => $userId,
                ]);

                $movement->productables()->create([
                    'product_product_id' => $variant->id,
                    'lot_id' => null,
                    'quantity' => $qty,
                    'price' => $price,
                    'subtotal' => $lineTotal,
                    'tax_rate' => 0,
                    'tax_amount' => 0,
                    'total' => $lineTotal,
                ]);

                return $movement->refresh();
            });
        };

        // 1. draft entry — recién creada, no enviada
        $createDraftEntry($nonTracked[0], 10, 50, 'Ajuste inicial seed', 1);
        $count++;

        // 2. submitted entry — pendiente de aprobación
        $m = $createDraftEntry($nonTracked[1], 15, 60, 'Ingreso por donación', 2);
        $service->submit($m, $userId);
        $count++;

        // 3. posted entry — aprobada e impacta el kardex
        $m = $createDraftEntry($nonTracked[2], 20, 45, 'Ingreso por compra externa', 3);
        $service->submit($m, $userId);
        $service->post($m, $userId);
        $count++;

        // 4. rejected entry — devuelta al solicitante con motivo
        $m = $createDraftEntry($nonTracked[3], 7, 40, 'Ajuste no autorizado', 4);
        $service->submit($m, $userId);
        $service->reject($m, $userId, 'Falta documento de respaldo del ingreso.');
        $count++;

        // 5. cancelled entry — fue posted y luego cancelada (genera contra-asiento)
        $m = $createDraftEntry($nonTracked[0], 5, 55, 'Ingreso erróneo', 5);
        $service->submit($m, $userId);
        $service->post($m, $userId);
        $service->cancel($m, $userId);
        $count++;

        // 6. posted exit — usa un lote con stock del LotSeeder
        $trackedLot = Lot::query()
            ->where('status', 'active')
            ->whereHas(
                'lotInventories',
                fn ($q) => $q->where('warehouse_id', $warehouseId)->where('quantity_balance', '>=', 5)
            )
            ->with('productProduct')
            ->orderBy('id')
            ->first();

        if ($trackedLot && $trackedLot->productProduct) {
            $m = DB::transaction(function () use (
                $exitJournal,
                $trackedLot,
                $warehouseId,
                $companyId,
                $userId,
            ) {
                $parts = SequenceService::getNextParts($exitJournal->id);
                $variant = $trackedLot->productProduct;
                $qty = 2.0;
                $price = (float) $trackedLot->initial_cost;
                $lineTotal = round($qty * $price, 2);

                $movement = Movement::create([
                    'type' => 'exit',
                    'serie' => $parts['serie'],
                    'correlative' => $parts['correlative'],
                    'date' => now()->subDays(2),
                    'reason' => 'Merma — seed',
                    'warehouse_id' => $warehouseId,
                    'company_id' => $companyId,
                    'journal_id' => $exitJournal->id,
                    'transfer_id' => null,
                    'status' => 'draft',
                    'total' => $lineTotal,
                    'created_user_id' => $userId,
                ]);

                $movement->productables()->create([
                    'product_product_id' => $variant->id,
                    'lot_id' => $trackedLot->id,
                    'quantity' => $qty,
                    'price' => $price,
                    'subtotal' => $lineTotal,
                    'tax_rate' => 0,
                    'tax_amount' => 0,
                    'total' => $lineTotal,
                ]);

                return $movement->refresh();
            });

            $service->submit($m, $userId);
            $service->post($m, $userId);
            $count++;
        } else {
            $this->command->warn('No se encontró un lote con stock ≥5 para crear el exit posted; saltado.');
        }

        $this->command->info(sprintf('✅ Se crearon %d movimientos sueltos en distintos estados.', $count));

        $this->resyncSequences();
    }

    /**
     * Alinea sequences.next_number con MAX(correlative)+step por journal de movements,
     * para evitar 'Duplicate entry' si se mezclan seeders y creación runtime.
     */
    private function resyncSequences(): void
    {
        // Incluye soft-deleted porque el unique index del documento no excluye deleted_at.
        $journalIds = Movement::query()
            ->withTrashed()
            ->distinct()
            ->pluck('journal_id')
            ->filter()
            ->all();

        foreach ($journalIds as $journalId) {
            $journal = Journal::with('sequence')->find($journalId);
            if (! $journal || ! $journal->sequence) {
                continue;
            }

            $maxCorrelative = (int) Movement::query()
                ->withTrashed()
                ->where('journal_id', $journalId)
                ->max(DB::raw('CAST(correlative AS UNSIGNED)'));

            $next = $maxCorrelative + (int) $journal->sequence->step;

            if ($next > (int) $journal->sequence->next_number) {
                $journal->sequence->update(['next_number' => $next]);
            }
        }
    }
}
