<?php

declare(strict_types=1);

use App\Models\BillingCheckout;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\Http;

function seedMercadoPagoPlanSwitch(): void
{
    $tenant = tenant();
    tenancy()->central(function () use ($tenant): void {
        $tenant->owner_email = 'owner@example.test';
        $tenant->save();

        Plan::query()->updateOrCreate(['slug' => 'pro-mensual'], [
            'name' => 'Pro Mensual',
            'price' => 59.99,
            'duration_days' => 30,
            'is_active' => true,
        ]);
        Plan::query()->updateOrCreate(['slug' => 'inactive-plan'], [
            'name' => 'Inactive',
            'price' => 10,
            'duration_days' => 30,
            'is_active' => false,
        ]);
    });
}

beforeEach(function (): void {
    config()->set('mercadopago.access_token', 'TEST-token');
    Http::fake([
        'api.mercadopago.com/preapproval' => Http::response([
            'id' => 'preapproval-plan-switch',
            'status' => 'pending',
            'init_point' => 'https://www.mercadopago.com.pe/subscriptions/checkout?preapproval_id=preapproval-plan-switch',
        ], 201),
    ]);
});

it('requires authentication', function (): void {
    seedMercadoPagoPlanSwitch();

    $this->tenantPostJson('/api/v1/apps/plan', ['plan_slug' => 'pro-mensual'])
        ->assertUnauthorized();
});

it('validates the requested plan', function (): void {
    $this->actingAsTenantUser(User::factory()->create(['email' => 'owner@example.test']));
    seedMercadoPagoPlanSwitch();

    $this->tenantPostJson('/api/v1/apps/plan', [])->assertStatus(422);
    $this->tenantPostJson('/api/v1/apps/plan', ['plan_slug' => 'ghost'])->assertNotFound();
    $this->tenantPostJson('/api/v1/apps/plan', ['plan_slug' => 'inactive-plan'])->assertNotFound();
});

it('keeps the current plan until Mercado Pago authorizes the replacement', function (): void {
    $this->actingAsTenantUser(User::factory()->create(['email' => 'owner@example.test']));
    seedMercadoPagoPlanSwitch();
    $oldPlan = tenant()->getPlanSlug();

    $response = $this->tenantPostJson('/api/v1/apps/plan', ['plan_slug' => 'pro-mensual'])
        ->assertOk()
        ->assertJsonPath('data.pending', true);

    expect($response->json('data.checkout_url'))->toStartWith('https://www.mercadopago.com.pe/')
        ->and(tenant()->fresh()->getPlanSlug())->toBe($oldPlan)
        ->and(BillingCheckout::query()->where('provider_id', 'preapproval-plan-switch')->exists())->toBeTrue();
});

it('rejects switching to the current plan', function (): void {
    $this->actingAsTenantUser(User::factory()->create(['email' => 'owner@example.test']));
    seedMercadoPagoPlanSwitch();

    $this->tenantPostJson('/api/v1/apps/plan', ['plan_slug' => 'test-plan'])
        ->assertStatus(422);
});
