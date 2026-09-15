<?php

declare(strict_types=1);

use App\Models\PaymentProviderConnection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

it('imports encrypted environment credentials into one tenant and validates the seller account', function (): void {
    config()->set('mercadopago.access_token', 'TEST-tenant-secret-token');
    config()->set('mercadopago.public_key', 'TEST-tenant-public-key');
    config()->set('mercadopago.webhook_secret', 'tenant-webhook-secret');

    $tenant = $this->tenant;
    $tenantId = (string) $tenant?->id;
    tenancy()->end();

    $this->artisan('tenant-payments:import-mercadopago-env', [
        'tenant' => $tenantId,
        '--environment' => 'test',
    ])->assertSuccessful();

    tenancy()->initialize($tenant);
    $connection = PaymentProviderConnection::query()->firstOrFail();
    $raw = DB::table('payment_provider_connections')->first();

    expect($connection->access_token)->toBe('TEST-tenant-secret-token')
        ->and($connection->is_active)->toBeFalse()
        ->and($raw?->access_token)->not->toBe('TEST-tenant-secret-token')
        ->and($raw?->webhook_secret)->not->toBe('tenant-webhook-secret');

    Http::fake([
        'api.mercadopago.com/users/me' => Http::response([
            'id' => 3683185305,
            'nickname' => 'TESTSELLER',
            'country_id' => 'PE',
        ]),
    ]);
    tenancy()->end();

    $this->artisan('tenant-payments:test-mercadopago', [
        'tenant' => $tenantId,
        '--environment' => 'test',
    ])->assertSuccessful();

    tenancy()->initialize($tenant);
    $connection = PaymentProviderConnection::query()->firstOrFail();

    expect($connection->is_active)->toBeTrue()
        ->and($connection->external_account_id)->toBe('3683185305')
        ->and($connection->account_nickname)->toBe('TESTSELLER')
        ->and($connection->country_id)->toBe('PE')
        ->and($connection->validated_at)->not->toBeNull()
        ->and($connection->last_validation_error)->toBeNull();

    Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer TEST-tenant-secret-token'));
});

it('keeps an invalid tenant connection inactive without leaking its token', function (): void {
    config()->set('mercadopago.access_token', 'invalid-private-token');
    $tenant = $this->tenant;
    $tenantId = (string) $tenant?->id;
    tenancy()->end();

    $this->artisan('tenant-payments:import-mercadopago-env', [
        'tenant' => $tenantId,
    ])->assertSuccessful();

    Http::fake([
        'api.mercadopago.com/users/me' => Http::response(['message' => 'unauthorized'], 401),
    ]);

    $this->artisan('tenant-payments:test-mercadopago', [
        'tenant' => $tenantId,
    ])->assertFailed();

    tenancy()->initialize($tenant);
    $connection = PaymentProviderConnection::query()->firstOrFail();

    expect($connection->is_active)->toBeFalse()
        ->and($connection->last_validation_error)->toBe('Mercado Pago respondió HTTP 401 al validar la cuenta.')
        ->and($connection->last_validation_error)->not->toContain('invalid-private-token');
});
