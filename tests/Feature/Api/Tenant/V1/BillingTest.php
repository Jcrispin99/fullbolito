<?php

declare(strict_types=1);

use App\Models\BillingCheckout;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\SubscriptionPlanChange;
use App\Models\User;
use App\Services\MercadoPago\HandleMercadoPagoWebhook;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
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
            'billing_rank' => 20,
            'is_active' => true,
            'sunat_worker_slots' => 2,
            'sunat_dedicated_queue' => false,
        ]);
    });
});

afterEach(function (): void {
    Carbon::setTestNow();
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
        ->assertJsonPath('data.subscription.provider', null)
        ->assertJsonPath('data.sunat_capacity.worker_slots', 1)
        ->assertJsonPath('data.sunat_capacity.queue_name', 'sunat');
});

it('lists every active paid plan without remote price ids', function (): void {
    $this->actingAsTenantUser();

    $response = $this->tenantGetJson('/api/v1/billing/plans')->assertOk();

    expect(collect($response->json('data.data'))->pluck('slug')->all())
        ->toContain('pro-mensual')
        ->not->toContain('test-plan');

    expect(collect($response->json('data.data'))->firstWhere('slug', 'pro-mensual')['sunat_worker_slots'])
        ->toBe(2);
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

it('keeps self-service billing available after the paid access period ends', function (): void {
    $this->actingAsTenantUser(User::factory()->create(['email' => 'payer@example.test']));
    $subscription = tenant()->subscription()->firstOrFail();
    $subscription->update([
        'provider' => 'mercadopago',
        'provider_id' => 'preapproval-ended',
        'provider_status' => 'cancelled',
        'status' => 'cancelled',
        'ends_at' => now()->subMinute(),
        'next_billing_at' => now()->subMinute(),
        'cancel_at_period_end' => true,
        'cancellation_requested_at' => now()->subWeek(),
    ]);

    Http::fake([
        'api.mercadopago.com/preapproval' => Http::response([
            'id' => 'preapproval-reactivation',
            'status' => 'pending',
            'init_point' => 'https://www.mercadopago.com.pe/subscriptions/checkout?preapproval_id=preapproval-reactivation',
        ], 201),
    ]);

    $this->tenantGetJson('/api/v1/billing')
        ->assertOk()
        ->assertJsonPath('data.subscription.status', 'cancelled');

    $this->tenantPostJson('/api/v1/billing/checkout', ['plan_slug' => 'pro-mensual'])
        ->assertOk()
        ->assertJsonPath('data.change_type', 'new_subscription');
});

it('reconciles an authorized checkout when Mercado Pago does not send a real test webhook', function (): void {
    $this->actingAsTenantUser(User::factory()->create(['email' => 'payer@example.test']));

    Http::fake(function (Request $request) {
        if ($request->method() === 'POST') {
            return Http::response([
                'id' => 'preapproval-reconcile',
                'status' => 'pending',
                'init_point' => 'https://www.mercadopago.com.pe/subscriptions/checkout?preapproval_id=preapproval-reconcile',
            ], 201);
        }

        return Http::response([
            'id' => 'preapproval-reconcile',
            'status' => 'authorized',
            'external_reference' => BillingCheckout::query()->latest('id')->value('external_reference'),
            'payer_id' => 123456,
            'payment_method_id' => 'master',
            'date_created' => now()->toIso8601String(),
            'next_payment_date' => now()->addMonth()->toIso8601String(),
        ]);
    });

    $this->tenantPostJson('/api/v1/billing/checkout', ['plan_slug' => 'pro-mensual'])
        ->assertOk();

    $this->artisan('billing:reconcile-mercadopago')->assertSuccessful();

    expect(tenant()->subscriptions()->where('provider_id', 'preapproval-reconcile')->value('status'))
        ->toBe('active')
        ->and(BillingCheckout::query()->where('provider_id', 'preapproval-reconcile')->value('completed_at'))
        ->not->toBeNull();
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

it('cancels renewal in Mercado Pago but preserves access until the paid period ends', function (): void {
    $this->actingAsTenantUser(User::factory()->create(['email' => 'payer@example.test']));
    $subscription = tenant()->subscription()->first();
    $accessUntil = now()->addDays(20)->startOfSecond();
    $subscription->update([
        'provider' => 'mercadopago',
        'provider_id' => 'preapproval-cancel',
        'provider_status' => 'authorized',
        'external_reference' => (string) Illuminate\Support\Str::uuid(),
        'status' => 'active',
        'ends_at' => $accessUntil,
        'next_billing_at' => $accessUntil,
    ]);
    $targetPlan = Plan::query()->where('slug', 'pro-mensual')->firstOrFail();
    $pendingChange = SubscriptionPlanChange::query()->create([
        'tenant_id' => tenant()->id,
        'subscription_id' => $subscription->id,
        'from_plan_id' => $subscription->plan_id,
        'to_plan_id' => $targetPlan->id,
        'kind' => 'upgrade',
        'status' => 'pending_payment',
        'external_reference' => (string) Illuminate\Support\Str::uuid(),
        'provider' => 'mercadopago',
        'current_recurring_amount' => 0,
        'target_recurring_amount' => 59.99,
        'proration_amount' => 20,
        'currency' => 'PEN',
        'effective_at' => now(),
    ]);

    Http::fake([
        'api.mercadopago.com/preapproval/preapproval-cancel' => Http::response([
            'id' => 'preapproval-cancel',
            'status' => 'cancelled',
            'external_reference' => $subscription->external_reference,
        ]),
    ]);

    $this->tenantPatchJson('/api/v1/billing/subscription/status', ['status' => 'cancelled'])
        ->assertOk()
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.provider_status', 'cancelled')
        ->assertJsonPath('data.cancel_at_period_end', true);

    Http::assertSent(fn (Request $request): bool => $request['status'] === 'cancelled');
    $subscription->refresh();
    expect($subscription->status)->toBe('active')
        ->and($subscription->provider_status)->toBe('cancelled')
        ->and($subscription->cancel_at_period_end)->toBeTrue()
        ->and($subscription->ends_at->equalTo($accessUntil))->toBeTrue()
        ->and($subscription->cancellation_requested_by)->toBe('payer@example.test')
        ->and($subscription->isValid())->toBeTrue()
        ->and($pendingChange->fresh()->status)->toBe('cancelled');

    app(HandleMercadoPagoWebhook::class)->syncPreapproval([
        'id' => 'preapproval-cancel',
        'status' => 'cancelled',
        'external_reference' => $subscription->external_reference,
    ]);

    expect($subscription->fresh()->status)->toBe('active');
});

it('makes period-end cancellation idempotent and blocks later plan changes', function (): void {
    $this->actingAsTenantUser(User::factory()->create(['email' => 'payer@example.test']));
    $subscription = tenant()->subscription()->firstOrFail();
    $subscription->update([
        'provider' => 'mercadopago',
        'provider_id' => 'preapproval-cancel-once',
        'provider_status' => 'authorized',
        'status' => 'active',
        'ends_at' => now()->addDays(10),
        'next_billing_at' => now()->addDays(10),
    ]);

    Http::fake([
        'api.mercadopago.com/preapproval/preapproval-cancel-once' => Http::response([
            'id' => 'preapproval-cancel-once',
            'status' => 'cancelled',
        ]),
    ]);

    $this->tenantPatchJson('/api/v1/billing/subscription/status', ['status' => 'cancelled'])->assertOk();
    $this->tenantPatchJson('/api/v1/billing/subscription/status', ['status' => 'cancelled'])->assertOk();

    Http::assertSentCount(1);
    $this->tenantPostJson('/api/v1/billing/checkout', ['plan_slug' => 'pro-mensual'])
        ->assertStatus(422)
        ->assertJsonPath('message', 'La suscripción ya está cancelada y conservará acceso hasta el final del periodo pagado.');
});

it('does not cancel renewal while a paid plan change is being applied', function (): void {
    $this->actingAsTenantUser(User::factory()->create(['email' => 'payer@example.test']));
    $subscription = tenant()->subscription()->firstOrFail();
    $subscription->update([
        'provider' => 'mercadopago',
        'provider_id' => 'preapproval-with-paid-change',
        'provider_status' => 'authorized',
        'status' => 'active',
        'ends_at' => now()->addDays(10),
        'next_billing_at' => now()->addDays(10),
    ]);
    $targetPlan = Plan::query()->where('slug', 'pro-mensual')->firstOrFail();
    SubscriptionPlanChange::query()->create([
        'tenant_id' => tenant()->id,
        'subscription_id' => $subscription->id,
        'from_plan_id' => $subscription->plan_id,
        'to_plan_id' => $targetPlan->id,
        'kind' => 'upgrade',
        'status' => 'paid',
        'external_reference' => (string) Illuminate\Support\Str::uuid(),
        'provider' => 'mercadopago',
        'current_recurring_amount' => 0,
        'target_recurring_amount' => 59.99,
        'proration_amount' => 20,
        'currency' => 'PEN',
        'effective_at' => now(),
        'paid_at' => now(),
    ]);

    Http::fake();

    $this->tenantPatchJson('/api/v1/billing/subscription/status', ['status' => 'cancelled'])
        ->assertStatus(422)
        ->assertJsonPath(
            'message',
            'Hay un cambio de plan pagado en proceso. Espera a que termine de aplicarse antes de cancelar la renovación.'
        );

    Http::assertNothingSent();
    expect($subscription->fresh()->cancel_at_period_end)->toBeFalse();
});

it('forbids billing mutations for a non-owner without the admin role', function (): void {
    $this->actingAsTenantUser(User::factory()->create(['email' => 'employee@example.test']));

    $this->tenantPostJson('/api/v1/billing/checkout', ['plan_slug' => 'pro-mensual'])
        ->assertForbidden();
});

it('charges only the remaining-period difference for an upgrade and applies it after payment approval', function (): void {
    Carbon::setTestNow('2026-09-12 10:00:00');
    $this->actingAsTenantUser(User::factory()->create(['email' => 'payer@example.test']));

    $basic = Plan::query()->updateOrCreate(['slug' => 'basico-mensual'], [
        'name' => 'Básico mensual',
        'price' => 30,
        'duration_days' => 30,
        'billing_rank' => 10,
        'is_active' => true,
    ]);
    $pro = Plan::query()->where('slug', 'pro-mensual')->firstOrFail();
    $subscription = tenant()->subscription()->firstOrFail();
    $subscription->update([
        'plan_id' => $basic->id,
        'provider' => 'mercadopago',
        'provider_id' => 'preapproval-upgrade',
        'provider_status' => 'authorized',
        'status' => 'active',
        'next_billing_at' => now()->addDays(15),
        'ends_at' => now()->addDays(15),
    ]);

    Http::fake([
        'api.mercadopago.com/checkout/preferences' => Http::response([
            'id' => 'preference-upgrade',
            'init_point' => 'https://www.mercadopago.com.pe/checkout/v1/redirect?pref_id=preference-upgrade',
        ], 201),
    ]);

    $response = $this->tenantPostJson('/api/v1/billing/checkout', ['plan_slug' => 'pro-mensual'])
        ->assertOk()
        ->assertJsonPath('data.change_type', 'upgrade')
        ->assertJsonPath('data.status', 'pending_payment')
        ->assertJsonPath('data.proration_amount', 15);

    $change = SubscriptionPlanChange::query()->findOrFail($response->json('data.change_id'));
    expect($subscription->fresh()->plan_id)->toBe($basic->id)
        ->and($change->provider_preference_id)->toBe('preference-upgrade');

    Http::assertSent(function (Request $request) use ($change): bool {
        return $request->url() === 'https://api.mercadopago.com/checkout/preferences'
            && $request['external_reference'] === $change->external_reference
            && $request['items'][0]['unit_price'] === 15.0;
    });

    Http::fake([
        'api.mercadopago.com/v1/payments/payment-upgrade' => Http::response([
            'id' => 'payment-upgrade',
            'status' => 'approved',
            'transaction_amount' => 15,
            'currency_id' => 'PEN',
            'external_reference' => $change->external_reference,
            'metadata' => ['subscription_plan_change_id' => $change->id],
            'date_approved' => now()->toIso8601String(),
        ]),
        'api.mercadopago.com/preapproval/preapproval-upgrade' => Http::response([
            'id' => 'preapproval-upgrade',
            'status' => 'authorized',
            'next_payment_date' => now()->addDays(15)->toIso8601String(),
            'auto_recurring' => [
                'frequency' => 1,
                'frequency_type' => 'months',
                'transaction_amount' => 60,
                'currency_id' => 'PEN',
            ],
        ]),
    ]);

    app(HandleMercadoPagoWebhook::class)->handle([
        'id' => 'event-upgrade',
        'type' => 'payment',
        'data' => ['id' => 'payment-upgrade'],
    ], 'payment-upgrade', 'event-upgrade');

    expect($subscription->fresh()->plan_id)->toBe($pro->id)
        ->and($change->fresh()->status)->toBe('applied')
        ->and(Payment::query()->where('transaction_id', 'payment-upgrade')->value('status'))->toBe('completed');

    Http::assertSent(function (Request $request): bool {
        return $request->method() === 'PUT'
            && $request->url() === 'https://api.mercadopago.com/preapproval/preapproval-upgrade'
            && $request['auto_recurring']['transaction_amount'] === 59.99;
    });
});

it('schedules a downgrade without charging and applies it at the next renewal', function (): void {
    Carbon::setTestNow('2026-09-12 10:00:00');
    $this->actingAsTenantUser(User::factory()->create(['email' => 'payer@example.test']));

    $basic = Plan::query()->updateOrCreate(['slug' => 'basico-mensual'], [
        'name' => 'Básico mensual',
        'price' => 29.99,
        'duration_days' => 30,
        'billing_rank' => 10,
        'is_active' => true,
    ]);
    $pro = Plan::query()->where('slug', 'pro-mensual')->firstOrFail();
    $subscription = tenant()->subscription()->firstOrFail();
    $renewal = now()->addDays(15);
    $subscription->update([
        'plan_id' => $pro->id,
        'provider' => 'mercadopago',
        'provider_id' => 'preapproval-downgrade',
        'provider_status' => 'authorized',
        'status' => 'active',
        'next_billing_at' => $renewal,
        'ends_at' => $renewal,
    ]);
    Http::fake([
        '*preapproval/preapproval-downgrade*' => Http::response([
            'id' => 'preapproval-downgrade',
            'status' => 'authorized',
            'next_payment_date' => '2026-10-27T10:00:00-05:00',
        ]),
    ]);

    $response = $this->tenantPostJson('/api/v1/billing/checkout', ['plan_slug' => 'basico-mensual'])
        ->assertOk()
        ->assertJsonPath('data.change_type', 'downgrade')
        ->assertJsonPath('data.status', 'scheduled')
        ->assertJsonPath('data.checkout_url', null)
        ->assertJsonPath('data.proration_amount', 0);

    $change = SubscriptionPlanChange::query()->findOrFail($response->json('data.change_id'));
    expect($subscription->fresh()->plan_id)->toBe($pro->id)
        ->and($change->effective_at->equalTo($renewal))->toBeTrue();
    Http::assertNothingSent();

    Carbon::setTestNow($renewal);
    $this->artisan('billing:apply-scheduled-plan-changes')->assertSuccessful();

    expect($subscription->fresh()->plan_id)->toBe($basic->id)
        ->and($change->fresh()->status)->toBe('applied');
    Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
        && $request['auto_recurring']['transaction_amount'] === 29.99);
});
