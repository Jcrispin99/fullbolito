<?php

declare(strict_types=1);

namespace Tests\Doubles;

use App\Models\Sale;
use App\Services\GreenterInvoiceService;

/**
 * In-memory replacement for GreenterInvoiceService.
 *
 * Persists `sunat_status` + a believable `sunat_response` payload + bogus
 * archive paths on the Sale. Never touches the network, never reads a cert,
 * never writes a real XML/CDR.
 */
final class FakeGreenterInvoiceService extends GreenterInvoiceService
{
    public bool $shouldSucceed = true;

    public string $errorMessage = 'Mocked SUNAT failure';

    /** @var list<int> */
    public array $sentSaleIds = [];

    public function __construct() {} // skip parent constructor (no config needed)

    public function sendInvoiceFromSale(Sale $sale): bool
    {
        $this->sentSaleIds[] = (int) $sale->id;

        if (! $this->shouldSucceed) {
            $sale->sunat_status = 'error';
            $sale->sunat_response = [
                'accepted' => false,
                'error' => $this->errorMessage,
                'updated_at' => now()->toIso8601String(),
            ];
            $sale->save();

            return false;
        }

        $sale->sunat_status = 'accepted';
        $sale->sunat_sent_at = now();
        $sale->sunat_response = [
            'accepted' => true,
            'cdr_response_code' => '0',
            'cdr_response_description' => 'La Factura ha sido aceptada (mocked)',
            'updated_at' => now()->toIso8601String(),
        ];
        $sale->signed_xml_path = "billing/sales/{$sale->id}/mocked.xml";
        $sale->cdr_zip_path = "billing/sales/{$sale->id}/mocked-cdr.zip";
        $sale->save();

        return true;
    }
}
