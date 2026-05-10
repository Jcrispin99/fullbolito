<?php

declare(strict_types=1);

use App\Models\Module;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Tests for Tenant feature/module gating. These exercise the central
 * Plan/Subscription/Module wiring via the Tenant model's helpers.
 *
 * Test config bindings live in tests/Pest.php under Feature/Models.
 *
 * Production `config/saas.php` may use wildcards on plans; we pin
 * explicit feature lists here so assertions against include/exclude
 * remain meaningful regardless of prod config drift.
 */
beforeEach(function (): void {
    config()->set('saas.plans.basico-mensual.features', ['dashboard', 'inventory', 'sales', 'purchases']);
    config()->set('saas.plans.pro-mensual.features', ['dashboard', 'inventory', 'sales', 'purchases', 'transfers', 'pos']);
});

function makeTenantWithPlan(string $planSlug): Tenant
{
    $plan = Plan::query()->updateOrCreate(
        ['slug' => $planSlug],
        ['name' => ucfirst($planSlug), 'price' => 0, 'duration_days' => 30, 'is_active' => true],
    );

    $tenant = Tenant::create(['id' => 'test_'.bin2hex(random_bytes(4))]);

    Subscription::create([
        'tenant_id' => $tenant->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addYear(),
    ]);

    return $tenant->fresh();
}

it('resolves the active plan slug from the latest valid subscription', function (): void {
    $tenant = makeTenantWithPlan('pro-mensual');

    expect($tenant->getPlanSlug())->toBe('pro-mensual');
});

it('falls back to the default plan when the tenant has no subscription', function (): void {
    $tenant = Tenant::create(['id' => 'orphan_'.bin2hex(random_bytes(4))]);

    expect($tenant->getPlanSlug())->toBe(config('saas.default_plan'));
});

it('falls back to the default plan when the subscription is expired', function (): void {
    $tenant = makeTenantWithPlan('pro-mensual');
    $tenant->subscription->update([
        'status' => 'active',
        'ends_at' => now()->subDay(),
    ]);

    expect($tenant->fresh()->getPlanSlug())->toBe(config('saas.default_plan'));
});

it('returns the feature list configured for the active plan', function (): void {
    $tenant = makeTenantWithPlan('basico-mensual');

    expect($tenant->getPlanFeatures())
        ->toContain('sales')
        ->toContain('purchases')
        ->not->toContain('pos');
});

it('expands wildcard plans to all active modules', function (): void {
    Module::factory()->create(['key' => 'alpha']);
    Module::factory()->create(['key' => 'beta']);
    Module::factory()->inactive()->create(['key' => 'inactive_one']);

    $tenant = makeTenantWithPlan('enterprise-anual');

    $features = $tenant->getPlanFeatures();
    expect($features)
        ->toContain('alpha')
        ->toContain('beta')
        ->not->toContain('inactive_one');
});

it('merges plan features with addon features without duplicates', function (): void {
    $tenant = makeTenantWithPlan('basico-mensual');
    $tenant->addAddon('pos');
    $tenant->addAddon('sales'); // already in plan, should not duplicate

    $features = $tenant->fresh()->getFeatures();
    expect($features)
        ->toContain('pos')
        ->toContain('sales')
        ->and(array_count_values($features)['sales'])->toBe(1);
});

it('hasFeature is true when the plan includes the module', function (): void {
    $tenant = makeTenantWithPlan('pro-mensual');

    expect($tenant->hasFeature('pos'))->toBeTrue()
        ->and($tenant->hasFeature('builder'))->toBeFalse();
});

it('hasFeature is true when the addon includes the module', function (): void {
    $tenant = makeTenantWithPlan('basico-mensual');
    $tenant->addAddon('builder');

    expect($tenant->fresh()->hasFeature('builder'))->toBeTrue();
});

it('isFeatureFromPlan distinguishes plan features from addons', function (): void {
    $tenant = makeTenantWithPlan('basico-mensual');
    $tenant->addAddon('builder');
    $tenant = $tenant->fresh();

    expect($tenant->isFeatureFromPlan('sales'))->toBeTrue()
        ->and($tenant->isFeatureFromPlan('builder'))->toBeFalse();
});

it('removeAddon strips the key from addon_features', function (): void {
    $tenant = makeTenantWithPlan('basico-mensual');
    $tenant->addAddon('pos');
    $tenant->addAddon('builder');

    $tenant->fresh()->removeAddon('pos');

    expect($tenant->fresh()->getAddonFeatures())
        ->toContain('builder')
        ->not->toContain('pos');
});
