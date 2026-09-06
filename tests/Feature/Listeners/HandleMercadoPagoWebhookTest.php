<?php

declare(strict_types=1);

use App\Models\BillingCheckout;
use App\Models\PaymentWebhookEvent;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Stancl\Tenancy\Events\TenantCreated;

uses(RefreshDatabase::class);

function mercadoPagoSignature(string $dataId, string $requestId, string $secret, string $timestamp = '1704908010'): string
{
    $manifest = 'id:'.mb_strtolower($dataId).";request-id:{$requestId};ts:{$timestamp};";

    return 'ts='.$timestamp.',v1='.hash_hmac('sha256', $manifest, $secret);
}

function mercadoPagoCheckout(): BillingCheckout
{
    Event::fake([TenantCreated::class]);

    $plan = Plan::query()->create([
        'name' => 'Pro Mensual',
        'slug' => 'pro-mensual',
        'price' => 59.99,
        'duration_days' => 30,
        'is_active' => true,
    ]);
    $tenant = Tenant::query()->create(['id' => 'mp-webhook-tenant']);

    return BillingCheckout::query()->create([
        'tenant_id' => $tenant->id,
        'plan_id' => $plan->id,
        'provider' => 'mercadopago',
        'external_reference' => 'ec17fc78-7777-4444-8888-bdc87f456789',
        'provider_id' => 'preapproval-001',
        'status' => 'pending',
        'amount' => 59.99,
        'currency' => 'PEN',
    ]);
}

it('rejects a webhook with an invalid signature', function (): void {
    config()->set('mercadopago.webhook_secret', 'webhook-secret');

    $this->postJson('/api/v1/webhooks/mercadopago?data.id=preapproval-001', [
        'id' => 1001,
        'type' => 'subscription_preapproval',
        'data' => ['id' => 'preapproval-001'],
    ], [
        'x-request-id' => 'request-001',
        'x-signature' => 'ts=1704908010,v1=invalid',
    ])->assertUnauthorized();
});

it('activates a subscription only from a signed authorized preapproval and is idempotent', function (): void {
    $checkout = mercadoPagoCheckout();
    config()->set('mercadopago.access_token', 'TEST-token');
    config()->set('mercadopago.webhook_secret', 'webhook-secret');

    Http::fake([
        'api.mercadopago.com/preapproval/preapproval-001' => Http::response([
            'id' => 'preapproval-001',
            'status' => 'authorized',
            'external_reference' => $checkout->external_reference,
            'payer_id' => 987654,
            'payment_method_id' => 'visa',
            'date_created' => '2026-09-04T10:00:00-05:00',
            'next_payment_date' => '2026-10-04T10:00:00-05:00',
        ]),
    ]);

    $payload = [
        'id' => 1001,
        'type' => 'subscription_preapproval',
        'action' => 'updated',
        'data' => ['id' => 'preapproval-001'],
    ];
    $headers = [
        'x-request-id' => 'request-001',
        'x-signature' => mercadoPagoSignature('preapproval-001', 'request-001', 'webhook-secret'),
    ];

    $this->postJson('/api/v1/webhooks/mercadopago?data.id=preapproval-001', $payload, $headers)
        ->assertOk()
        ->assertJsonPath('received', true);
    $this->postJson('/api/v1/webhooks/mercadopago?data.id=preapproval-001', $payload, $headers)
        ->assertOk();

    $subscription = Subscription::query()->where('provider_id', 'preapproval-001')->firstOrFail();
    expect($subscription->status)->toBe('active')
        ->and($subscription->plan_id)->toBe($checkout->plan_id)
        ->and($subscription->next_billing_at->toDateString())->toBe('2026-10-04')
        ->and(Subscription::query()->where('provider_id', 'preapproval-001')->count())->toBe(1)
        ->and(PaymentWebhookEvent::query()->count())->toBe(1)
        ->and(Tenant::query()->findOrFail($checkout->tenant_id)->billing_provider)->toBe('mercadopago');

    Http::assertSentCount(1);
});
