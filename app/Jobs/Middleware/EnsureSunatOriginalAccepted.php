<?php

declare(strict_types=1);

namespace App\Jobs\Middleware;

use App\Jobs\SendInvoiceToSunatJob;
use Closure;
use Illuminate\Support\Facades\Log;

/**
 * Evita que una NC/ND gane la carrera a su factura o boleta original cuando
 * el tenant tiene más de un canal simultáneo.
 */
final class EnsureSunatOriginalAccepted
{
    public function handle(SendInvoiceToSunatJob $job, Closure $next): void
    {
        $sale = $job->sale();
        $sale->loadMissing(['journal', 'originalSale']);
        $docType = (string) ($sale->journal?->document_type_code ?? '');

        if (! in_array($docType, ['07', '08'], true) || ! $sale->originalSale) {
            $next($job);

            return;
        }

        $original = $sale->originalSale->fresh();
        $accepted = $original?->sunat_status === 'accepted'
            || data_get($original?->sunat_response, 'accepted') === true;

        if ($accepted) {
            $next($job);

            return;
        }

        $terminalFailure = in_array($original?->sunat_status, ['rejected', 'skipped'], true)
            || ($original?->sunat_status === 'error'
                && data_get($original?->sunat_response, 'retryable') !== true);
        $dependencyExpired = time() >= $job->dependencyDeadline();

        if ($terminalFailure || $dependencyExpired) {
            $reason = $terminalFailure
                ? 'El comprobante original no fue aceptado por SUNAT.'
                : 'Se agotó el tiempo de espera para la aceptación del comprobante original.';

            $sale->forceFill([
                'sunat_status' => 'error',
                'sunat_response' => [
                    'accepted' => false,
                    'retryable' => false,
                    'dependency_sale_id' => $original?->id,
                    'error' => $reason,
                    'updated_at' => now()->toIso8601String(),
                ],
            ])->save();

            Log::warning('Nota no enviada a SUNAT por comprobante original no aceptado', [
                'sale_id' => $sale->id,
                'original_sale_id' => $original?->id,
                'original_sunat_status' => $original?->sunat_status,
            ]);

            return;
        }

        $delay = max(1, (int) config('saas.sunat.dependency_release_delay_seconds', 30));
        $job->release($delay);
    }
}
