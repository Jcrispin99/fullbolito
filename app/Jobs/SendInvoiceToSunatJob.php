<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Jobs\Middleware\EnsureSunatOriginalAccepted;
use App\Jobs\Middleware\LimitSunatTenantConcurrency;
use App\Models\Sale;
use App\Models\Tenant;
use App\Services\GreenterInvoiceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Despacha el envío de un comprobante a SUNAT en background.
 *
 * Se invoca tras publicar una venta, completar un checkout POS o generar una
 * nota de crédito. El endpoint manual continúa siendo síncrono.
 * El servicio actualiza `sunat_status` / `sunat_response` / `sunat_sent_at`
 * directamente en la venta, así que el job no necesita devolver nada.
 */
final class SendInvoiceToSunatJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Los releases por falta de slot o por dependencia documental no deben
    // consumir el límite de reintentos. maxExceptions mantiene en tres los
    // fallos reales de transporte/ejecución.
    public int $tries = 0;

    public int $maxExceptions = 3;

    public int $backoff = 60;

    public int $timeout = 75;

    private string $tenantId = '';

    private int $dependencyDeadline = 0;

    public function __construct(
        private readonly Sale $sale,
    ) {
        $currentTenant = tenant();
        $this->tenantId = $currentTenant instanceof Tenant
            ? (string) $currentTenant->getTenantKey()
            : '';
        $this->dependencyDeadline = now()
            ->addMinutes(max(1, (int) config('saas.sunat.dependency_timeout_minutes', 1440)))
            ->getTimestamp();

        $dedicated = (bool) config('saas.sunat.dedicated_queues_enabled', false)
            && $currentTenant instanceof Tenant
            && $currentTenant->usesDedicatedSunatQueue();

        // El payload sigue incluyendo tenant_id mediante QueueTenancyBootstrapper.
        // El enrutamiento dedicado sólo se activa cuando operaciones confirma
        // que existe un worker escuchando la cola particular del tenant.
        $this->onQueue($dedicated ? self::dedicatedQueueName($this->tenantId) : 'sunat');
    }

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [
            new EnsureSunatOriginalAccepted(),
            new LimitSunatTenantConcurrency(),
        ];
    }

    public function handle(GreenterInvoiceService $service): void
    {
        try {
            $service->sendInvoiceFromSale($this->sale);

            $this->sale->refresh();

            // El servicio persiste el diagnóstico antes de retornar. Sólo los
            // fallos transitorios (red/timeout/5xx) deben volver a la cola; un
            // rechazo CDR es terminal y reenviar el mismo XML no lo corrige.
            if (data_get($this->sale->sunat_response, 'retryable') === true) {
                throw new RuntimeException(
                    (string) (data_get($this->sale->sunat_response, 'error')
                        ?: 'Fallo transitorio al enviar el comprobante a SUNAT.'),
                );
            }
        } catch (\Throwable $e) {
            Log::error('SendInvoiceToSunatJob falló', [
                'sale_id' => $this->sale->id,
                'error' => $e->getMessage(),
            ]);

            // Re-lanzar para que la cola aplique backoff/retry.
            throw $e;
        }
    }

    public function failed(?\Throwable $exception): void
    {
        try {
            $sale = $this->sale->fresh();
            if (! $sale) {
                return;
            }

            $response = (array) ($sale->sunat_response ?? []);
            $sale->forceFill([
                'sunat_status' => 'error',
                'sunat_response' => array_merge($response, [
                    'accepted' => false,
                    'retryable' => false,
                    'attempts_exhausted' => true,
                    'error' => $exception?->getMessage()
                        ?: data_get($response, 'error')
                        ?: 'Se agotaron los reintentos de envío a SUNAT.',
                    'updated_at' => now()->toIso8601String(),
                ]),
            ])->save();
        } catch (\Throwable $failure) {
            Log::error('No se pudo persistir el fallo definitivo del job SUNAT', [
                'sale_id' => $this->saleId(),
                'error' => $failure->getMessage(),
            ]);
        }
    }

    public function saleId(): int
    {
        return (int) $this->sale->getKey();
    }

    public function sale(): Sale
    {
        return $this->sale;
    }

    public function tenantId(): string
    {
        if ($this->tenantId !== '') {
            return $this->tenantId;
        }

        $currentTenant = tenant();

        return $currentTenant instanceof Tenant
            ? (string) $currentTenant->getTenantKey()
            : '';
    }

    public function dependencyDeadline(): int
    {
        if ($this->dependencyDeadline > 0) {
            return $this->dependencyDeadline;
        }

        return $this->dependencyDeadline = now()
            ->addMinutes(max(1, (int) config('saas.sunat.dependency_timeout_minutes', 1440)))
            ->getTimestamp();
    }

    public static function dedicatedQueueName(string $tenantId): string
    {
        $readable = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $tenantId) ?: 'tenant';
        $readable = trim(mb_substr($readable, 0, 40), '-');
        $fingerprint = mb_substr(hash('sha256', $tenantId), 0, 10);

        return 'sunat-tenant-'.$readable.'-'.$fingerprint;
    }
}
