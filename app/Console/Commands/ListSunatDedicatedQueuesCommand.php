<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SendInvoiceToSunatJob;
use App\Models\Subscription;
use Illuminate\Console\Command;

final class ListSunatDedicatedQueuesCommand extends Command
{
    protected $signature = 'sunat:dedicated-queues
                            {--commands : Mostrar el comando queue:work de cada tenant.}';

    protected $description = 'Lista las colas SUNAT dedicadas requeridas por suscripciones Enterprise.';

    public function handle(): int
    {
        $subscriptions = Subscription::query()
            ->with(['tenant', 'plan'])
            ->whereIn('status', ['active', 'trial'])
            ->whereHas('plan', fn ($query) => $query->where('sunat_dedicated_queue', true))
            ->get()
            ->filter(fn (Subscription $subscription): bool => $subscription->isValid() && $subscription->tenant !== null)
            ->unique('tenant_id')
            ->values();

        if ($subscriptions->isEmpty()) {
            $this->info('No hay tenants con cola SUNAT dedicada activa.');

            return self::SUCCESS;
        }

        if (! config('saas.sunat.dedicated_queues_enabled', false)) {
            $this->warn('SUNAT_DEDICATED_QUEUES_ENABLED está desactivado; estos tenants siguen usando la cola compartida sunat.');
        }

        $rows = $subscriptions->map(function (Subscription $subscription): array {
            $tenantId = (string) $subscription->tenant_id;

            return [
                $tenantId,
                (int) $subscription->plan->sunat_worker_slots,
                SendInvoiceToSunatJob::dedicatedQueueName($tenantId),
            ];
        })->all();

        $this->table(['Tenant', 'Canales', 'Cola'], $rows);

        if ($this->option('commands')) {
            foreach ($rows as [$tenantId, $slots, $queue]) {
                $this->newLine();
                $this->comment("# {$tenantId}: ejecutar {$slots} proceso(s)");
                $this->line("php artisan queue:work database --queue={$queue} --sleep=1 --timeout=75");
            }
        }

        return self::SUCCESS;
    }
}
