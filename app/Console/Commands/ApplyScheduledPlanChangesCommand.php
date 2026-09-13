<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SubscriptionPlanChange;
use App\Services\MercadoPago\MercadoPagoBillingService;
use Illuminate\Console\Command;
use Throwable;

final class ApplyScheduledPlanChangesCommand extends Command
{
    protected $signature = 'billing:apply-scheduled-plan-changes
                            {--prepare-minutes=10 : Minutos de anticipación para actualizar la recurrencia remota.}';

    protected $description = 'Prepara y activa downgrades/cambios de ciclo al terminar el periodo ya pagado.';

    public function handle(MercadoPagoBillingService $billing): int
    {
        $prepareUntil = now()->addMinutes(max(1, (int) $this->option('prepare-minutes')));
        $failures = 0;

        SubscriptionPlanChange::query()
            ->where('status', 'scheduled')
            ->where('effective_at', '<=', $prepareUntil)
            ->orderBy('effective_at')
            ->eachById(function (SubscriptionPlanChange $change) use ($billing, &$failures): void {
                try {
                    $billing->prepareScheduledChange($change);
                    $this->line("Preparado cambio #{$change->id} para tenant {$change->tenant_id}.");
                } catch (Throwable $e) {
                    $failures++;
                    $this->error("Cambio #{$change->id}: {$e->getMessage()}");
                }
            });

        SubscriptionPlanChange::query()
            ->where('status', 'ready')
            ->where('effective_at', '<=', now())
            ->orderBy('effective_at')
            ->eachById(function (SubscriptionPlanChange $change) use ($billing, &$failures): void {
                try {
                    $billing->activateScheduledChange($change);
                    $this->info("Aplicado cambio #{$change->id} para tenant {$change->tenant_id}.");
                } catch (Throwable $e) {
                    $failures++;
                    $this->error("Cambio #{$change->id}: {$e->getMessage()}");
                }
            });

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
