<?php

declare(strict_types=1);

use App\Services\GreenterInvoiceService;
use Tests\TestCase;

uses(TestCase::class);

/**
 * @return array{status: string, accepted: bool}
 */
function classifySunatOutcome(?int $cdrCode, bool $transportSucceeded, ?bool $explicitSuccess): array
{
    $service = new GreenterInvoiceService();
    $method = new ReflectionMethod($service, 'classifySunatOutcome');
    $method->setAccessible(true);

    return $method->invoke($service, $cdrCode, $transportSucceeded, $explicitSuccess);
}

it('classifies CDR zero as accepted', function (): void {
    expect(classifySunatOutcome(0, true, true))->toBe([
        'status' => 'accepted',
        'accepted' => true,
    ]);
});

it('classifies CDR 2000 through 3999 as rejected', function (int $cdrCode): void {
    expect(classifySunatOutcome($cdrCode, true, false))->toBe([
        'status' => 'rejected',
        'accepted' => false,
    ]);
})->with([2000, 2335, 3999]);

it('treats a rejection CDR as authoritative even on a non-2xx transport response', function (): void {
    expect(classifySunatOutcome(2335, false, false))->toBe([
        'status' => 'rejected',
        'accepted' => false,
    ]);
});

it('keeps a successful response without CDR as sent', function (): void {
    expect(classifySunatOutcome(null, true, null))->toBe([
        'status' => 'sent',
        'accepted' => false,
    ]);
});

it('classifies a failed transport as error', function (): void {
    expect(classifySunatOutcome(null, false, null))->toBe([
        'status' => 'error',
        'accepted' => false,
    ]);
});
