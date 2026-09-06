<?php

declare(strict_types=1);

use App\Models\BillingCheckout;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('mercadopago.access_token', 'TEST-token');

    Plan::query()->create([
        'name' => 'Free Trial',
        'slug' => 'free-trial',
        'price' => 0,
        'duration_days' => 14,
        'is_active' => true,
    ]);
    Plan::query()->create([
        'name' => 'Pro Mensual',
        'slug' => 'pro-mensual',
        'price' => 59.99,
        'duration_days' => 30,
        'is_active' => true,
    ]);

    Http::fake([
        'api.mercadopago.com/preapproval' => Http::response([
            'id' => 'preapproval-registration',
            'status' => 'pending',
            'init_point' => 'https://www.mercadopago.com.pe/subscriptions/checkout?preapproval_id=preapproval-registration',
        ], 201),
    ]);
});

function registrationPayload(array $extra = []): array
{
    $suffix = bin2hex(random_bytes(4));

    return array_merge([
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => "jane+{$suffix}@example.test",
        'business_name' => "Acme {$suffix}",
        'phone' => '+51999000111',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ], $extra);
}

it('defaults to a local free trial without calling Mercado Pago', function (): void {
    $response = $this->postJson('/api/v1/register-tenant', registrationPayload())->assertCreated();

    expect($response->json('data.checkout_url'))->toBeNull()
        ->and(Subscription::query()->count())->toBe(1);
    Http::assertNothingSent();
});

it('creates a Mercado Pago checkout for a paid plan and defers activation', function (): void {
    $response = $this->postJson('/api/v1/register-tenant', registrationPayload([
        'plan_slug' => 'pro-mensual',
    ]))->assertCreated();

    expect($response->json('data.checkout_url'))->toStartWith('https://www.mercadopago.com.pe/')
        ->and(Subscription::query()->count())->toBe(0)
        ->and(BillingCheckout::query()->where('provider_id', 'preapproval-registration')->exists())->toBeTrue()
        ->and(Tenant::query()->count())->toBe(1);
});

it('rejects unknown or inactive plans before provisioning a tenant', function (): void {
    Plan::query()->create([
        'name' => 'Inactive',
        'slug' => 'inactive-plan',
        'price' => 10,
        'duration_days' => 30,
        'is_active' => false,
    ]);

    $this->postJson('/api/v1/register-tenant', registrationPayload(['plan_slug' => 'ghost']))
        ->assertStatus(422);
    $this->postJson('/api/v1/register-tenant', registrationPayload(['plan_slug' => 'inactive-plan']))
        ->assertStatus(422);

    expect(Tenant::query()->count())->toBe(0);
});
