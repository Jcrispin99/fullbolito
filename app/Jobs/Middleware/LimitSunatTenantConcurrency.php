<?php

declare(strict_types=1);

namespace App\Jobs\Middleware;

use App\Jobs\SendInvoiceToSunatJob;
use App\Models\Tenant;
use App\Services\SunatConcurrencyService;
use Closure;
use RuntimeException;

final class LimitSunatTenantConcurrency
{
    public function handle(SendInvoiceToSunatJob $job, Closure $next): void
    {
        $tenant = tenant();

        if (! $tenant instanceof Tenant) {
            if ($job->tenantId() === '') {
                $next($job);

                return;
            }

            throw new RuntimeException("No se pudo inicializar el tenant [{$job->tenantId()}] para el job SUNAT.");
        }

        $service = app(SunatConcurrencyService::class);
        $lease = $service->acquire($tenant, $job->saleId());

        if (! $lease) {
            $delay = max(1, (int) config('saas.sunat.slot_release_delay_seconds', 5));
            $job->release($delay);

            return;
        }

        try {
            $next($job);
        } finally {
            $service->release($lease);
        }
    }
}
