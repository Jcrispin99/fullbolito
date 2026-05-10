<?php

declare(strict_types=1);

use App\Models\Module;
use App\Models\Plan;

/**
 * Verifies GET /api/v1/apps:
 *  - returns the catalog of active modules with plan/addon flags
 *  - reflects the tenant's current plan
 *  - sums addon_total only for active addons
 *  - lists upgradeable plans
 *
 * Pin explicit plan features so the tests don't depend on prod wildcards.
 */
beforeEach(function (): void {
    config()->set('saas.plans.basico-mensual.features', ['sales']);
    config()->set('saas.plans.pro-mensual.features', ['sales', 'pos']);
});


function seedAppsScaffold(): array
{
    return tenancy()->central(function (): array {
        $modules = collect([
            ['key' => 'sales',   'label' => 'Ventas',         'addon_price' => 0,    'sort_order' => 10],
            ['key' => 'pos',     'label' => 'POS',            'addon_price' => 0,    'sort_order' => 20],
            ['key' => 'loyalty', 'label' => 'Fidelización',   'addon_price' => 9.99, 'sort_order' => 30],
            ['key' => 'builder', 'label' => 'Builder',        'addon_price' => 14.99, 'sort_order' => 40],
        ])->map(fn (array $row) => Module::query()->updateOrCreate(
            ['key' => $row['key']],
            array_merge(['is_active' => true, 'description' => null, 'icon' => null], $row),
        ));

        // Make sure these slugs exist as Plans so /apps lists them.
        foreach ([
            ['slug' => 'basico-mensual', 'name' => 'Básico', 'price' => 29.99],
            ['slug' => 'pro-mensual', 'name' => 'Pro', 'price' => 59.99],
        ] as $p) {
            Plan::query()->updateOrCreate(
                ['slug' => $p['slug']],
                array_merge($p, ['duration_days' => 30, 'is_active' => true]),
            );
        }

        return ['modules' => $modules];
    });
}

function setTenantPlan(string $planSlug): void
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

it('requires authentication', function (): void {
    seedAppsScaffold();
    $this->tenantGetJson('/api/v1/apps')->assertUnauthorized();
});

it('returns the apps catalog with plan/addon flags', function (): void {
    $this->actingAsTenantUser();
    seedAppsScaffold();
    setTenantPlan('basico-mensual'); // includes sales (no pos/loyalty/builder)

    $response = $this->tenantGetJson('/api/v1/apps')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'apps' => [
                    ['key', 'label', 'enabled', 'included_in_plan', 'is_addon', 'is_active_addon', 'can_toggle', 'addon_price'],
                ],
                'current_plan' => ['name', 'slug', 'price'],
                'plans',
                'addon_total',
                'has_stripe_subscription',
            ],
        ]);

    $apps = collect($response->json('data.apps'))->keyBy('key');
    expect($apps['sales']['included_in_plan'])->toBeTrue()
        ->and($apps['sales']['enabled'])->toBeTrue()
        ->and($apps['sales']['can_toggle'])->toBeFalse()
        ->and($apps['loyalty']['included_in_plan'])->toBeFalse()
        ->and($apps['loyalty']['enabled'])->toBeFalse()
        ->and($apps['loyalty']['is_addon'])->toBeTrue()
        ->and($apps['loyalty']['can_toggle'])->toBeTrue();
});

it('reports the active plan slug as current_plan', function (): void {
    $this->actingAsTenantUser();
    seedAppsScaffold();
    setTenantPlan('pro-mensual');

    $this->tenantGetJson('/api/v1/apps')
        ->assertOk()
        ->assertJsonPath('data.current_plan.slug', 'pro-mensual');
});

it('sums addon_total only across active addons', function (): void {
    $this->actingAsTenantUser();
    seedAppsScaffold();
    setTenantPlan('basico-mensual');

    /** @var \App\Models\Tenant $tenant */
    $tenant = tenant();
    tenancy()->central(function () use ($tenant) {
        $tenant->addAddon('loyalty'); // 9.99
        $tenant->addAddon('builder'); // 14.99
    });

    $this->tenantGetJson('/api/v1/apps')
        ->assertOk()
        ->assertJsonPath('data.addon_total', 24.98);
});

it('lists available plans with their module summary', function (): void {
    $this->actingAsTenantUser();
    seedAppsScaffold();
    setTenantPlan('basico-mensual');

    $response = $this->tenantGetJson('/api/v1/apps')->assertOk();

    $plans = collect($response->json('data.plans'))->keyBy('slug');
    expect($plans)->toHaveKey('pro-mensual')
        ->and(collect($plans['pro-mensual']['modules'])->pluck('key')->all())
        ->toContain('pos');
});
