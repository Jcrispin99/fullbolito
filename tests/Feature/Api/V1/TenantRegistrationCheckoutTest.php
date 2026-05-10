<?php

declare(strict_types=1);

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\StripeCheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Records every checkout call so tests can assert what was sent
 * without ever touching Stripe.
 */
class FakeStripeCheckoutService extends StripeCheckoutService
{
    public array $calls = [];

    public function createSubscriptionCheckout(
        Tenant $tenant,
        Plan $plan,
        string $successUrl,
        string $cancelUrl,
    ): string {
        $this->calls[] = [
            'tenant_id' => $tenant->id,
            'plan_slug' => $plan->slug,
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
        ];

        return 'https://checkout.stripe.com/c/pay/cs_test_fake_' . count($this->calls);
    }
}

beforeEach(function (): void {
    $this->fakeCheckout = new FakeStripeCheckoutService();
    $this->app->instance(StripeCheckoutService::class, $this->fakeCheckout);

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
        'stripe_product_id' => 'prod_pro',
        'stripe_price_id' => 'price_pro',
    ]);
});

$payload = function (array $extra = []): array {
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
};

it('defaults to free-trial when plan_slug is omitted', function () use ($payload): void {
    $response = $this->postJson('/api/v1/register-tenant', $payload());

    $response->assertCreated();
    expect($response->json('data.checkout_url'))->toBeNull();
    expect($this->fakeCheckout->calls)->toBeEmpty();

    $tenant = Tenant::query()->first();
    expect($tenant)->not->toBeNull();
    expect(Subscription::query()->where('tenant_id', $tenant->id)->count())->toBe(1);
});

it('accepts explicit free-trial plan_slug without checkout', function () use ($payload): void {
    $response = $this->postJson('/api/v1/register-tenant', $payload(['plan_slug' => 'free-trial']));

    $response->assertCreated();
    expect($response->json('data.checkout_url'))->toBeNull();
    expect($this->fakeCheckout->calls)->toBeEmpty();
});

it('returns checkout_url for paid plan and defers local subscription', function () use ($payload): void {
    $response = $this->postJson('/api/v1/register-tenant', $payload(['plan_slug' => 'pro-mensual']));

    $response->assertCreated();
    expect($response->json('data.checkout_url'))->toStartWith('https://checkout.stripe.com/');

    expect($this->fakeCheckout->calls)->toHaveCount(1);
    expect($this->fakeCheckout->calls[0]['plan_slug'])->toBe('pro-mensual');
    expect($this->fakeCheckout->calls[0]['success_url'])->toContain('/billing/success');
    expect($this->fakeCheckout->calls[0]['cancel_url'])->toContain('/billing/cancel');

    $tenant = Tenant::query()->first();
    expect($tenant)->not->toBeNull();
    // Paid plans defer subscription creation to the Stripe webhook.
    expect(Subscription::query()->where('tenant_id', $tenant->id)->count())->toBe(0);
});

it('rejects unknown plan_slug', function () use ($payload): void {
    $this->postJson('/api/v1/register-tenant', $payload(['plan_slug' => 'does-not-exist']))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['plan_slug']);

    expect(Tenant::query()->count())->toBe(0);
});

it('rejects inactive plan_slug', function () use ($payload): void {
    Plan::query()->create([
        'name' => 'Old Plan',
        'slug' => 'old-plan',
        'price' => 10,
        'duration_days' => 30,
        'is_active' => false,
        'stripe_price_id' => 'price_old',
    ]);

    $this->postJson('/api/v1/register-tenant', $payload(['plan_slug' => 'old-plan']))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['plan_slug']);
});
