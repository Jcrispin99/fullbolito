<?php

declare(strict_types=1);

use App\Models\Plan;

/**
 * Verifies the `tenant.feature:*` middleware on the tenant API.
 *
 * Each test rebinds the active tenant's subscription to a plan whose
 * config-defined feature list does NOT include `pos`, then asserts that:
 *  - hitting a gated endpoint returns 403 with MODULE_NOT_ACTIVE
 *  - adding the addon flips it back to 200
 *  - /api/me reports the resolved plan + features
 *
 * Pin explicit plan feature lists so we don't depend on prod config
 * (which may use wildcards).
 */
beforeEach(function (): void {
    config()->set('saas.plans.basico-mensual.features', ['dashboard', 'inventory', 'sales', 'purchases']);
    config()->set('saas.plans.pro-mensual.features', ['dashboard', 'inventory', 'sales', 'purchases', 'transfers', 'pos']);
});


function downgradeTenantTo(string $planSlug): void
{
    /** @var \App\Models\Tenant $tenant */
    $tenant = tenant();

    tenancy()->central(function () use ($planSlug, $tenant) {
        $plan = Plan::query()->updateOrCreate(
            ['slug' => $planSlug],
            ['name' => ucfirst($planSlug), 'price' => 0, 'duration_days' => 30, 'is_active' => true],
        );
        $tenant->subscription->update(['plan_id' => $plan->id]);
    });

    $tenant->refresh();
}

it('returns 403 with MODULE_NOT_ACTIVE when the plan does not include the module', function (): void {
    $this->actingAsTenantUser();
    downgradeTenantTo('basico-mensual'); // no pos

    $this->tenantGetJson('/api/v1/pos-configs')
        ->assertStatus(403)
        ->assertJsonPath('error_code', 'MODULE_NOT_ACTIVE')
        ->assertJsonPath('module', 'pos');
});

it('lets the request through when the plan includes the module', function (): void {
    $this->actingAsTenantUser();
    downgradeTenantTo('pro-mensual'); // includes pos

    $this->tenantGetJson('/api/v1/pos-configs')->assertOk();
});

it('lets the request through when the addon adds the module', function (): void {
    $this->actingAsTenantUser();
    downgradeTenantTo('basico-mensual');
    /** @var \App\Models\Tenant $tenant */
    $tenant = tenant();
    tenancy()->central(fn () => $tenant->addAddon('pos'));

    $this->tenantGetJson('/api/v1/pos-configs')->assertOk();
});

it('exposes plan and features on /api/me', function (): void {
    $this->actingAsTenantUser();
    downgradeTenantTo('basico-mensual');

    $response = $this->tenantGetJson('/api/me')
        ->assertOk()
        ->assertJsonPath('data.plan', 'basico-mensual');

    $features = $response->json('data.features');
    expect($features)
        ->toContain('sales')
        ->toContain('purchases')
        ->not->toContain('pos');
});

it('keeps non-gated routes working regardless of plan', function (): void {
    $this->actingAsTenantUser();
    downgradeTenantTo('basico-mensual');

    // billing-credentials is base infra (no tenant.feature gate).
    $this->tenantGetJson('/api/v1/billing-credentials')->assertOk();
});
