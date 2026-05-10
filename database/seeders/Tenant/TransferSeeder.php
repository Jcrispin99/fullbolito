<?php

declare(strict_types=1);

namespace Database\Seeders\Tenant;

use App\Models\Company;
use App\Models\Journal;
use App\Models\Lot;
use App\Models\Movement;
use App\Models\Transfer;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\MovementService;
use App\Services\TransferService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Crea Transferencias en cada uno de los estados derivados:
 *   - draft           : ambos movements en draft
 *   - pending_exit    : exit submitted, entry no posted
 *   - in_transit      : exit posted, entry no posted
 *   - completed       : ambos posted
 *   - with_observation: exit posted, entry rejected
 *   - cancelled       : completed luego cancelada (revertida)
 *
 * Las transferencias mueven productos *trazados por lote* (creados por
 * LotSeeder) desde el almacén origen al destino. El servicio se encarga
 * de recalcular costo y mover stock cuando los movements son aprobados.
 */
final class TransferSeeder extends Seeder
{
    public function run(): void
    {
        if (Transfer::query()->exists()) {
            return;
        }

        $company = Company::whereNull('parent_id')->first() ?? Company::first();
        /** @var array<int, Warehouse> $warehouses */
        $warehouses = Warehouse::orderBy('id')->take(2)->get()->values()->all();
        $user = User::first();

        if (! $company || count($warehouses) < 2 || ! $user) {
            $this->command->error(
                'Faltan datos base: se requieren ≥2 warehouses, 1 company y 1 user. Ejecuta los seeders previos.'
            );

            return;
        }

        $fromWarehouse = $warehouses[0];
        $toWarehouse = $warehouses[1];

        // Validar journals esperados (transfer + movement_entry/exit).
        $missingJournals = collect(['transfer', 'movement_entry', 'movement_exit'])
            ->reject(fn ($type) => Journal::query()
                ->where('type', $type)
                ->where('company_id', $company->id)
                ->exists());

        if ($missingJournals->isNotEmpty()) {
            $this->command->error(
                'Faltan journals: '.$missingJournals->implode(', ').'. Ejecuta JournalSeeder.'
            );

            return;
        }

        // Necesitamos ≥6 lotes con stock en el almacén origen (uno por transfer).
        /** @var array<int, Lot> $lots */
        $lots = Lot::query()
            ->where('status', 'active')
            ->whereHas(
                'lotInventories',
                fn ($q) => $q->where('warehouse_id', $fromWarehouse->id)->where('quantity_balance', '>=', 4)
            )
            ->with('productProduct')
            ->orderBy('id')
            ->take(6)
            ->get()
            ->values()
            ->all();

        $lotCount = count($lots);
        if ($lotCount < 4) {
            $this->command->warn(
                "Sólo hay {$lotCount} lotes con stock ≥4 en {$fromWarehouse->name}. "
                .'TransferSeeder se saltará. Ejecuta LotSeeder primero.'
            );

            return;
        }

        // Alinear sequences contra registros existentes ANTES de crear nada,
        // para evitar colisiones con transfers/movements creados desde la UI.
        $this->resyncSequences();

        /** @var TransferService $transferService */
        $transferService = app(TransferService::class);
        /** @var MovementService $movementService */
        $movementService = app(MovementService::class);

        $userId = (int) $user->id;
        $companyId = (int) $company->id;
        $fromId = (int) $fromWarehouse->id;
        $toId = (int) $toWarehouse->id;

        // Helper para construir el payload `products` desde un lote.
        $productsFromLot = function (Lot $lot, float $qty): array {
            return [[
                'product_product_id' => (int) $lot->product_product_id,
                'quantity' => $qty,
                'lot_id' => (int) $lot->id,
            ]];
        };

        $count = 0;

        // 1. draft — sin enviar
        $transferService->create([
            'company_id' => $companyId,
            'from_warehouse_id' => $fromId,
            'to_warehouse_id' => $toId,
            'observation' => 'Transferencia draft (seed)',
            'products' => $productsFromLot($lots[0], 2),
        ], $userId);
        $count++;

        // 2. pending_exit — exit submitted, entry aún en draft
        $t = $transferService->create([
            'company_id' => $companyId,
            'from_warehouse_id' => $fromId,
            'to_warehouse_id' => $toId,
            'observation' => 'Transferencia pendiente de aprobación de salida (seed)',
            'products' => $productsFromLot($lots[1], 3),
        ], $userId);
        /** @var Movement $exit */
        $exit = $t->exitMovement()->firstOrFail();
        $movementService->submit($exit, $userId);
        $count++;

        // 3. in_transit — exit posted, entry todavía en draft (esperando recepción)
        $t = $transferService->create([
            'company_id' => $companyId,
            'from_warehouse_id' => $fromId,
            'to_warehouse_id' => $toId,
            'observation' => 'Transferencia en tránsito (seed)',
            'products' => $productsFromLot($lots[2], 3),
        ], $userId);
        $transferService->send($t, $userId); // submit + post del exit
        $count++;

        // 4. completed — ambos movements posted
        $t = $transferService->create([
            'company_id' => $companyId,
            'from_warehouse_id' => $fromId,
            'to_warehouse_id' => $toId,
            'observation' => 'Transferencia completada (seed)',
            'products' => $productsFromLot($lots[3], 2),
        ], $userId);
        $transferService->send($t, $userId);
        $transferService->receive($t, $userId);
        $count++;

        // 5. with_observation — exit posted, entry rejected (recibida con observación)
        if ($lotCount > 4) {
            $t = $transferService->create([
                'company_id' => $companyId,
                'from_warehouse_id' => $fromId,
                'to_warehouse_id' => $toId,
                'observation' => 'Transferencia con observación (seed)',
                'products' => $productsFromLot($lots[4], 2),
            ], $userId);
            $transferService->send($t, $userId);
            /** @var Movement $entry */
            $entry = $t->entryMovement()->firstOrFail();
            $movementService->submit($entry, $userId);
            $movementService->reject($entry, $userId, 'Cantidad recibida no coincide con la enviada.');
            $count++;
        }

        // 6. cancelled — fue creada (draft) y cancelada antes de aprobar nada
        if ($lotCount > 5) {
            $t = $transferService->create([
                'company_id' => $companyId,
                'from_warehouse_id' => $fromId,
                'to_warehouse_id' => $toId,
                'observation' => 'Transferencia cancelada antes de enviar (seed)',
                'products' => $productsFromLot($lots[5], 2),
            ], $userId);
            $transferService->cancel($t, $userId);
            $count++;
        }

        $this->command->info(sprintf('✅ Se crearon %d transferencias en distintos estados.', $count));

        $this->resyncSequences();
    }

    /**
     * Alinea sequences.next_number con MAX(correlative)+step por journal usado
     * por transfers y sus movements pareados.
     */
    private function resyncSequences(): void
    {
        // Movements: incluye soft-deleted porque el unique index no excluye deleted_at.
        $movementJournalIds = Movement::query()
            ->withTrashed()
            ->distinct()
            ->pluck('journal_id')
            ->filter()
            ->all();

        foreach ($movementJournalIds as $journalId) {
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

        // Sequence del journal de transferencias (Transfer no tiene journal_id propio).
        $transferJournal = Journal::with('sequence')->where('type', 'transfer')->first();
        if ($transferJournal && $transferJournal->sequence) {
            $maxCorrelative = (int) Transfer::query()
                ->withTrashed()
                ->max(DB::raw('CAST(correlative AS UNSIGNED)'));

            $next = $maxCorrelative + (int) $transferJournal->sequence->step;

            if ($next > (int) $transferJournal->sequence->next_number) {
                $transferJournal->sequence->update(['next_number' => $next]);
            }
        }
    }
}
