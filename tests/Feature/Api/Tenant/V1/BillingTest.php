<?php

declare(strict_types=1);

use App\Models\Plan;
use App\Models\Tenant;
use App\Services\StripeBillingPortalService;
use App\Services\StripeCheckoutService;
use App\Services\StripeInvoiceService;

class FakeBillingCheckoutService extends StripeCheckoutService
{
    /** @var list<array{tenant_id: string, plan_slug: string, success_url: string, cancel_url: string}> */
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

        return 'https://checkout.stripe.com/c/pay/cs_billing_' . count($this->calls);
    }
}

class FakeInvoiceService extends StripeInvoiceService
{
    public array $returnInvoices = [];

    public function listInvoices(\App\Models\Tenant $tenant): array
    {
        return $this->returnInvoices;
    }
}

class FakeBillingPortalService extends StripeBillingPortalService
{
    /** @var list<array{tenant_id: string, return_url: string}> */
    public array $calls = [];

    public function getPortalUrl(Tenant $tenant, string $returnUrl): string
    {
        $this->calls[] = [
            'tenant_id' => $tenant->id,
            'return_url' => $returnUrl,
        ];

        return 'https://billing.stripe.com/p/session/portal_' . count($this->calls);
    }
}

beforeEach(function (): void {
    $this->fakeCheckout = new FakeBillingCheckoutService();
    $this->fakePortal = new FakeBillingPortalService();
    $this->fakeInvoices = new FakeInvoiceService();
    $this->app->instance(StripeCheckoutService::class, $this->fakeCheckout);
    $this->app->instance(StripeBillingPortalService::class, $this->fakePortal);
    $this->app->instance(StripeInvoiceService::class, $this->fakeInvoices);

    tenancy()->central(function (): void {
        Plan::query()->updateOrCreate(['slug' => 'pro-mensual'], [
            'name' => 'Pro Mensual',
            'price' => 59.99,
            'duration_days' => 30,
            'is_active' => true,
            'stripe_product_id' => 'prod_pro',
            'stripe_price_id' => 'price_pro',
        ]);

        Plan::query()->updateOrCreate(['slug' => 'no-stripe-plan'], [
            'name' => 'No Stripe',
            'price' => 10,
            'duration_days' => 30,
            'is_active' => true,
        ]);
    });
});

it('GET /billing requires authentication', function (): void {
    $this->tenantGetJson('/api/v1/billing')->assertUnauthorized();
});

it('GET /billing returns current subscription + plan', function (): void {
    $this->actingAsTenantUser();

    $response = $this->tenantGetJson('/api/v1/billing')->assertOk();

    expect($response->json('data.tenant_id'))->toBe(tenant()->id);
    expect($response->json('data.subscription'))->not->toBeNull();
    expect($response->json('data.plan'))->not->toBeNull();
    expect($response->json('data.has_stripe_customer'))->toBeFalse();
});

it('GET /billing/plans returns only billable plans', function (): void {
    $this->actingAsTenantUser();

    $response = $this->tenantGetJson('/api/v1/billing/plans')->assertOk();

    $slugs = collect($response->json('data.data'))->pluck('slug')->all();
    expect($slugs)->toContain('pro-mensual');
    // no-stripe-plan has price>0 but no stripe_price_id → excluded.
    expect($slugs)->not->toContain('no-stripe-plan');
});

it('POST /billing/checkout returns checkout_url for billable plan', function (): void {
    $this->actingAsTenantUser();

    $response = $this->tenantPostJson('/api/v1/billing/checkout', ['plan_slug' => 'pro-mensual'])
        ->assertOk();

    expect($response->json('data.checkout_url'))->toStartWith('https://checkout.stripe.com/');
    expect($this->fakeCheckout->calls)->toHaveCount(1);
    expect($this->fakeCheckout->calls[0]['plan_slug'])->toBe('pro-mensual');
});

it('POST /billing/checkout rejects plans without stripe_price_id', function (): void {
    $this->actingAsTenantUser();

    $this->tenantPostJson('/api/v1/billing/checkout', ['plan_slug' => 'no-stripe-plan'])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Plan [no-stripe-plan] is not billable via Stripe.');

    expect($this->fakeCheckout->calls)->toBeEmpty();
});

it('POST /billing/checkout returns 404 for unknown plan_slug', function (): void {
    $this->actingAsTenantUser();

    $this->tenantPostJson('/api/v1/billing/checkout', ['plan_slug' => 'ghost'])
        ->assertNotFound()
        ->assertJsonPath('success', false);
});

it('POST /billing/portal rejects when tenant has no Stripe customer', function (): void {
    $this->actingAsTenantUser();

    $this->tenantPostJson('/api/v1/billing/portal', [])
        ->assertStatus(422)
        ->assertJsonPath('message', 'No Stripe customer linked to this tenant.');

    expect($this->fakePortal->calls)->toBeEmpty();
});

it('GET /billing/invoices returns the fake invoice list', function (): void {
    $this->actingAsTenantUser();

    $this->fakeInvoices->returnInvoices = [
        [
            'id' => 'in_test_1',
            'number' => 'INV-001',
            'total' => 5999,
            'currency' => 'pen',
            'status' => 'paid',
            'created_at' => 1735689600,
            'hosted_invoice_url' => 'https://invoice.stripe.com/i/in_test_1',
            'invoice_pdf' => 'https://invoice.stripe.com/i/in_test_1/pdf',
        ],
    ];

    $response = $this->tenantGetJson('/api/v1/billing/invoices')->assertOk();

    expect($response->json('data.data'))->toHaveCount(1);
    expect($response->json('data.data.0.number'))->toBe('INV-001');
});

it('POST /billing/portal returns portal_url when tenant has a Stripe customer', function (): void {
    $this->actingAsTenantUser();

    /** @var Tenant $tenant */
    $tenant = tenant();
    tenancy()->central(function () use ($tenant): void {
        $tenant->forceFill(['stripe_id' => 'cus_test_xyz'])->save();
    });

    $response = $this->tenantPostJson('/api/v1/billing/portal', [])->assertOk();

    expect($response->json('data.portal_url'))->toStartWith('https://billing.stripe.com/');
    expect($this->fakePortal->calls)->toHaveCount(1);
});
