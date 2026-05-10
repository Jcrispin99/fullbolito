<?php

declare(strict_types=1);

namespace Tests\Doubles;

use App\Models\Transfer;
use App\Services\GreenterDespatchService;

/**
 * In-memory replacement for GreenterDespatchService (GRE).
 *
 * `sendDespatch` sets a fake ticket + `gre_status = ticket_pending`.
 * `pollTicket` flips it to `accepted` (or `error` if shouldSucceed=false).
 */
final class FakeGreenterDespatchService extends GreenterDespatchService
{
    public bool $shouldSucceed = true;

    public string $errorMessage = 'Mocked GRE failure';

    public string $ticketPrefix = 'TKT';

    /** @var list<int> */
    public array $sentTransferIds = [];

    /** @var list<int> */
    public array $polledTransferIds = [];

    public function __construct() {}

    public function sendDespatch(Transfer $transfer): bool
    {
        $this->sentTransferIds[] = (int) $transfer->id;

        if (! $this->shouldSucceed) {
            $transfer->gre_status = 'error';
            $transfer->gre_response = [
                'accepted' => false,
                'error' => $this->errorMessage,
                'updated_at' => now()->toIso8601String(),
            ];
            $transfer->save();

            return false;
        }

        $transfer->gre_status = 'ticket_pending';
        $transfer->gre_ticket = $this->ticketPrefix.'-'.$transfer->id;
        $transfer->gre_sent_at = now();
        $transfer->gre_signed_xml_path = "billing/gre/{$transfer->id}/mocked.xml";
        $transfer->gre_response = [
            'accepted' => null,
            'ticket' => $transfer->gre_ticket,
            'updated_at' => now()->toIso8601String(),
        ];
        $transfer->save();

        return true;
    }

    public function pollTicket(Transfer $transfer): bool
    {
        $this->polledTransferIds[] = (int) $transfer->id;

        if (! $this->shouldSucceed) {
            $transfer->gre_status = 'error';
            $transfer->gre_response = [
                'accepted' => false,
                'error' => $this->errorMessage,
                'updated_at' => now()->toIso8601String(),
            ];
            $transfer->save();

            return false;
        }

        $transfer->gre_status = 'accepted';
        $transfer->gre_cdr_zip_path = "billing/gre/{$transfer->id}/mocked-cdr.zip";
        $transfer->gre_response = [
            'accepted' => true,
            'cdr_response_code' => '0',
            'cdr_response_description' => 'GRE aceptada (mocked)',
            'updated_at' => now()->toIso8601String(),
        ];
        $transfer->save();

        return true;
    }
}
