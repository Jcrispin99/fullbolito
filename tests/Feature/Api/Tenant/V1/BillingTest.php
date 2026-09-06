<?php

declare(strict_types=1);

use App\Models\BillingCheckout;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config()->set('mercadopago.access_token', 'TEST-token');
    config()->set('mercadopago.currency', 'PEN');

    $tenant = tenant();
    tenancy()->central(function () use ($tenant): void {
        $tenant->owner_email = 'payer@example.test';
        $tenant->save();

        Plan::query()->updateOrCreate(['slug' => 'pro-mensual'], [
            'name' => 'Pro Mensual',
            'price' => 59.99,
            'duration_days' => 30,
            'is_active' => true,
        ]);
    });
});

it('requires authentication', function (): void {
    $this->tenantGetJson('/api/v1/billing')->assertUnauthorized();
});

it('returns the current provider-neutral billing state', function (): void {
    $this->actingAsTenantUser();

    $this->tenantGetJson('/api/v1/billing')
        ->assertOk()
        ->assertJsonPath('data.tenant_id', tenant()->id)
        ->assertJsonPath('data.has_payment_subscription', false)
        ->assertJsonPath('data.subscription.provider', null);
});

it('lists every active paid plan without remote price ids', function (): void {
    $this->actingAsTenantUser();

    $response = $this->tenantGetJson('/api/v1/billing/plans')->assertOk();

    expect(collect($response->json('data.data'))->pluck('slug')->all())
        ->toContain('pro-mensual')
        ->not->toContain('test-plan');
});

it('creates a pending Mercado Pago preapproval without replacing the active plan', function (): void {
    $this->actingAsTenantUser(User::factory()->create(['email' => 'payer@example.test']));
    Http::fake([
        'api.mercadopago.com/preapproval' => Http::response([
            'id' => 'preapproval-test-1',
            'status' => 'pending',
            'init_point' => 'https://www.mercadopago.com.pe/subscriptions/checkout?preapproval_id=preapproval-test-1',
        ], 201),
    ]);

    $currentPlan = tenant()->getPlanSlug();
    $response = $this->tenantPostJson('/api/v1/billing/checkout', ['plan_slug' => 'pro-mensual'])
        ->assertOk();

    expect($response->json('data.checkout_url'))->toStartWith('https://www.mercadopago.com.pe/')
        ->and(tenant()->fresh()->getPlanSlug())->toBe($currentPlan)
        ->and(BillingCheckout::query()->where('provider_id', 'preapproval-test-1')->value('status'))->toBe('pending');

    Http::assertSent(function (Request $request): bool {
        return $request->url() === 'https://api.mercadopago.com/preapproval'
            && $request->hasHeader('Authorization', 'Bearer TEST-token')
            && $request['payer_email'] === 'payer@example.test'
            && $request['auto_recurring']['currency_id'] === 'PEN'
            && $request['auto_recurring']['transaction_amount'] === 59.99
            && $request['status'] === 'pending';
    });
});

it('returns locally reconciled Mercado Pago payments', function (): void {
    $this->actingAsTenantUser();
    $subscription = tenant()->subscription()->first();

    Payment::query()->create([
        'subscription_id' => $subscription->id,
        'amount' => 59.99,
        'currency' => 'PEN',
        'method' => 'mercadopago',
        'status' => 'completed',
        'transaction_id' => 'payment-123',
        'paid_at' => now(),
    ]);

    $this->tenantGetJson('/api/v1/billing/invoices')
        ->assertOk()
        ->assertJsonPath('data.data.0.reference', 'payment-123')
        ->assertJsonPath('data.data.0.amount', 59.99)
        ->assertJsonPath('data.data.0.currency', 'PEN');
});

it('pauses an active Mercado Pago subscription and mirrors its status', function (): void {
    $this->actingAsTenantUser(User::factory()->create(['email' => 'payer@example.test']));
    $subscription = tenant()->subscription()->first();
    $subscription->update([
        'provider' => 'mercadopago',
        'provider_id' => 'preapproval-active',
        'provider_status' => 'authorized',
        'external_reference' => (string) Illuminate\Support\Str::uuid(),
    ]);

    Http::fake([
        'api.mercadopago.com/preapproval/preapproval-active' => Http::response([
            'id' => 'preapproval-active',
            'status' => 'paused',
            'external_reference' => $subscription->external_reference,
            'date_created' => now()->subMonth()->toIso8601String(),
            'next_payment_date' => now()->addMonth()->toIso8601String(),
        ]),
    ]);

    $this->tenantPatchJson('/api/v1/billing/subscription/status', ['status' => 'paused'])
        ->assertOk()
        ->assertJsonPath('data.status', 'paused');

    expect($subscription->fresh()->status)->toBe('paused')
        ->and($subscription->fresh()->provider_status)->toBe('paused');
});

it('translates the local cancelled status to Mercado Pago canceled', function (): void {
    $this->actingAsTenantUser(User::factory()->create(['email' => 'payer@example.test']));
    $subscription = tenant()->subscription()->first();
    $subscription->update([
        'provider' => 'mercadopago',
        'provider_id' => 'preapproval-cancel',
        'provider_status' => 'authorized',
        'external_reference' => (string) Illuminate\Support\Str::uuid(),
    ]);

    Http::fake([
        'api.mercadopago.com/preapproval/preapproval-cancel' => Http::response([
            'id' => 'preapproval-cancel',
            'status' => 'canceled',
            'external_reference' => $subscription->external_reference,
        ]),
    ]);

    $this->tenantPatchJson('/api/v1/billing/subscription/status', ['status' => 'cancelled'])
        ->assertOk()
        ->assertJsonPath('data.status', 'canceled');

    Http::assertSent(fn (Request $request): bool => $request['status'] === 'canceled');
    expect($subscription->fresh()->status)->toBe('cancelled');
});

it('forbids billing mutations for a non-owner without the admin role', function (): void {
    $this->actingAsTenantUser(User::factory()->create(['email' => 'employee@example.test']));

    $this->tenantPostJson('/api/v1/billing/checkout', ['plan_slug' => 'pro-mensual'])
        ->assertForbidden();
});
