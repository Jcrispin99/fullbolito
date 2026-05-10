<?php

declare(strict_types=1);

use App\Models\Module;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Pivot-backed feature resolution. When a Plan has rows in `plan_module`
 * the Tenant should resolve features from there instead of falling back
 * to config/saas.php.
 */
function makePivotTenant(Plan $plan): Tenant
{
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

it('resolves features from the plan_module pivot when populated', function (): void {
    $modules = Module::factory()->count(3)->sequence(
        ['key' => 'sales', 'label' => 'Ventas'],
        ['key' => 'pos', 'label' => 'POS'],
        ['key' => 'inventory', 'label' => 'Inventario'],
    )->create();

    $plan = Plan::query()->create([
        'name' => 'Custom',
        'slug' => 'custom-plan',
        'price' => 0,
        'duration_days' => 30,
        'is_active' => true,
    ]);

    // Bind two of the three modules; pivot wins over any config fallback.
    $plan->modules()->sync([$modules[0]->id, $modules[1]->id]);

    $tenant = makePivotTenant($plan);

    expect($tenant->getPlanFeatures())
        ->toContain('sales')
        ->toContain('pos')
        ->not->toContain('inventory');
});

it('expands every active module when includes_all_modules is true', function (): void {
    Module::factory()->count(3)->sequence(
        ['key' => 'a'],
        ['key' => 'b'],
        ['key' => 'c'],
    )->create();
    Module::factory()->inactive()->create(['key' => 'd']);

    $plan = Plan::query()->create([
        'name' => 'Wildcard',
        'slug' => 'wildcard-plan',
        'price' => 0,
        'duration_days' => 30,
        'is_active' => true,
        'includes_all_modules' => true,
    ]);

    // Even with no pivot rows, the wildcard flag short-circuits to all
    // active modules — and inactive ones (`d`) must stay excluded.
    $tenant = makePivotTenant($plan);

    expect($tenant->getPlanFeatures())
        ->toContain('a')
        ->toContain('b')
        ->toContain('c')
        ->not->toContain('d');
});

it('falls back to config when the plan exists but has no pivot rows', function (): void {
    // Without this fallback the existing test suite (which uses test-plan
    // from config) would break, since plan_module is empty in test setup.
    config()->set('saas.plans.fallback-plan.features', ['custom_feature']);

    $plan = Plan::query()->create([
        'name' => 'Fallback',
        'slug' => 'fallback-plan',
        'price' => 0,
        'duration_days' => 30,
        'is_active' => true,
    ]);

    $tenant = makePivotTenant($plan);

    expect($tenant->getPlanFeatures())->toContain('custom_feature');
});

it('does not fall back to config once the pivot has at least one row', function (): void {
    Module::factory()->create(['key' => 'real_module']);
    config()->set('saas.plans.real-plan.features', ['from_config_only']);

    $plan = Plan::query()->create([
        'name' => 'Real',
        'slug' => 'real-plan',
        'price' => 0,
        'duration_days' => 30,
        'is_active' => true,
    ]);

    $plan->modules()->sync([Module::query()->first()->id]);

    $tenant = makePivotTenant($plan);

    expect($tenant->getPlanFeatures())
        ->toContain('real_module')
        ->not->toContain('from_config_only');
});
