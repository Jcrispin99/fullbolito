<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Tenant;
use App\Support\SunatSlotLease;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Reserva los canales simultáneos de facturación contratados por un tenant.
 *
 * Los leases viven en la base central para que todos los workers observen el
 * mismo estado, aunque estén procesando conexiones tenant diferentes.
 */
final class SunatConcurrencyService
{
    public function acquire(Tenant $tenant, int $saleId, ?int $leaseSeconds = null): ?SunatSlotLease
    {
        $slots = $tenant->getSunatWorkerSlots();
        $ttl = max(1, $leaseSeconds ?? (int) config('saas.sunat.slot_lease_seconds', 90));
        $owner = (string) Str::uuid();
        $now = now();

        return $this->connection()->transaction(function () use ($tenant, $saleId, $slots, $ttl, $owner, $now): ?SunatSlotLease {
            $table = $this->connection()->table('sunat_processing_slots');
            $documentLocks = $this->connection()->table('sunat_document_locks');
            $tenantId = (string) $tenant->getTenantKey();
            $rows = [];

            for ($slot = 1; $slot <= $slots; $slot++) {
                $rows[] = [
                    'tenant_id' => $tenantId,
                    'slot_number' => $slot,
                    'owner' => null,
                    'locked_until' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            $table->insertOrIgnore($rows);
            $documentLocks->insertOrIgnore([[
                'tenant_id' => $tenantId,
                'sale_id' => $saleId,
                'owner' => null,
                'locked_until' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]]);

            $documentLock = $documentLocks
                ->where('tenant_id', $tenantId)
                ->where('sale_id', $saleId)
                ->lockForUpdate()
                ->first();

            if (! $documentLock) {
                return null;
            }

            if ($documentLock->locked_until !== null
                && Carbon::parse((string) $documentLock->locked_until)->isAfter($now)
            ) {
                return null;
            }

            $available = $table
                ->where('tenant_id', $tenantId)
                ->where('slot_number', '<=', $slots)
                ->where(function ($query) use ($now): void {
                    $query->whereNull('locked_until')->orWhere('locked_until', '<=', $now);
                })
                ->orderBy('slot_number')
                ->lockForUpdate()
                ->first();

            if (! $available) {
                return null;
            }

            $table->where('id', $available->id)->update([
                'owner' => $owner,
                'locked_until' => $now->copy()->addSeconds($ttl),
                'updated_at' => $now,
            ]);
            $documentLocks->where('id', $documentLock->id)->update([
                'owner' => $owner,
                'locked_until' => $now->copy()->addSeconds($ttl),
                'updated_at' => $now,
            ]);

            return new SunatSlotLease(
                $tenantId,
                (int) $available->slot_number,
                $saleId,
                $owner,
            );
        }, 3);
    }

    public function release(SunatSlotLease $lease): void
    {
        $this->connection()
            ->table('sunat_processing_slots')
            ->where('tenant_id', $lease->tenantId)
            ->where('slot_number', $lease->slotNumber)
            ->where('owner', $lease->owner)
            ->update([
                'owner' => null,
                'locked_until' => null,
                'updated_at' => now(),
            ]);

        $this->connection()
            ->table('sunat_document_locks')
            ->where('tenant_id', $lease->tenantId)
            ->where('sale_id', $lease->saleId)
            ->where('owner', $lease->owner)
            ->delete();
    }

    public function activeSlots(Tenant $tenant): int
    {
        return $this->connection()
            ->table('sunat_processing_slots')
            ->where('tenant_id', (string) $tenant->getTenantKey())
            ->where('locked_until', '>', now())
            ->count();
    }

    private function connection(): ConnectionInterface
    {
        $name = config('tenancy.database.central_connection');

        return DB::connection(is_string($name) ? $name : 'mysql');
    }
}
