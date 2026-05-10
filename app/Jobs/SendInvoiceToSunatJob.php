<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Sale;
use App\Services\GreenterInvoiceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Despacha el envío de un comprobante a SUNAT en background.
 *
 * Se invoca tras `Sale::post()` y desde el endpoint manual de reenvío.
 * El servicio actualiza `sunat_status` / `sunat_response` / `sunat_sent_at`
 * directamente en la venta, así que el job no necesita devolver nada.
 */
final class SendInvoiceToSunatJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        private readonly Sale $sale,
    ) {}

    public function handle(GreenterInvoiceService $service): void
    {
        try {
            $service->sendInvoiceFromSale($this->sale);
        } catch (\Throwable $e) {
            Log::error('SendInvoiceToSunatJob falló', [
                'sale_id' => $this->sale->id,
                'error' => $e->getMessage(),
            ]);

            // Re-lanzar para que la cola aplique backoff/retry.
            throw $e;
        }
    }
}
