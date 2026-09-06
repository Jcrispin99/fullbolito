<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Reservation;
use Illuminate\Console\Command;

/**
 * Marca como `cancelled` las reservas que quedaron en `held` con su
 * `held_until` vencido — clientes que abandonaron el flujo sin pagar.
 *
 * El slot ya se considera libre desde el momento que `held_until` pasa
 * (lo maneja el scope `currentlyBlocking`), pero la fila queda en `held`
 * indefinidamente. Este job hace la limpieza para que los listados y
 * reportes no acumulen reservas zombi.
 *
 * Pensado para correrse vía `tenants:run reservations:cleanup-expired-holds`
 * (stancl/tenancy) — cada minuto.
 */
final class CleanupExpiredHoldsCommand extends Command
{
    protected $signature = 'reservations:cleanup-expired-holds
                            {--dry-run : Sólo reporta sin escribir cambios.}';

    protected $description = 'Cancela holds vencidos (tenant actual).';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $query = Reservation::query()
            ->where('status', 'held')
            ->whereNotNull('held_until')
            ->where('held_until', '<=', now());

        $count = $query->count();

        if ($count === 0) {
            $this->info('Sin holds vencidos.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->info("Se cancelarían {$count} reserva(s) (dry-run).");

            return self::SUCCESS;
        }

        $updated = $query->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => 'Hold expirado (sin pago dentro del tiempo límite)',
            'held_until' => null,
        ]);

        $this->info("Cancelados {$updated} hold(s) expirado(s).");

        return self::SUCCESS;
    }
}
