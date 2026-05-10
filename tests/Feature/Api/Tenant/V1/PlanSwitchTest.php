<?php

declare(strict_types=1);

use App\Models\Module;
use App\Models\Plan;
use App\Models\Tenant;
use App\Services\StripePlanService;

/**
 * Captures swapPlan invocations so tests can assert that the Stripe sync
 * fired (or did not) without ever hitting the real Stripe API.
 */
class FakeStripePlanService extends StripePlanService
{
    /** @var list<array{tenant_id: string, old_slug: string, new_slug: string}> */
    public array $calls = [];

    public function swapPlan(Tenant $tenant, Plan $oldPlan, Plan $newPlan): void
    {
        $this->calls[] = [
            'tenant_id' => $tenant->id,
            'old_slug' => $oldPlan->slug,
            'new_slug' => $newPlan->slug,
        ];
    }
}

/**
 * Verifies POST /api/v1/apps/plan:
 *  - requires authentication + valid payload
 *  - rejects unknown / inactive / same-as-current plans
 *  - refuses to switch when a Stripe subscription is attached
 *  - rebinds the active subscription, renews dates, and returns the catalog
 *  - drops addons that are now bundled in the new plan
 *
 * Pin explicit plan features so the assertions don't depend on prod
 * config (which may use wildcards).
 */
beforeEach(function (): void {
    config()->set('saas.plans.basico-mensual.features', ['sales']);
    config()->set('saas.plans.pro-mensual.features', ['sales', 'pos', 'loyalty']);
    config()->set('saas.plans.enterprise-anual.features', ['*']);

    // Singleton bind so the controller and the test see the same instance.
    $this->fakeStripePlanService = new FakeStripePlanService();
    $this->app->instance(StripePlanService::class, $this->fakeStripePlanService);
});

function seedPlanSwitchScaffold(): void
{
    tenancy()->central(function (): void {
        collect([
            ['key' => 'sales',   'label' => 'Ventas',       'addon_price' => 0,    'sort_order' => 10],
            ['key' => 'pos',     'label' => 'POS',          'addon_price' => 0,    'sort_order' => 20],
            ['key' => 'loyalty', 'label' => 'Fidelización', 'addon_price' => 9.99, 'sort_order' => 30],
            ['key' => 'builder', 'label' => 'Builder',      'addon_price' => 14.99, 'sort_order' => 40],
        ])->each(fn (array $row) => Module::query()->updateOrCreate(
            ['key' => $row['key']],
            array_merge(['is_active' => true, 'description' => null, 'icon' => null], $row),
        ));

        foreach ([
            ['slug' => 'basico-mensual', 'name' => 'Básico', 'price' => 29.99, 'is_active' => true],
            ['slug' => 'pro-mensual', 'name' => 'Pro', 'price' => 59.99, 'is_active' => true],
            ['slug' => 'enterprise-anual', 'name' => 'Enterprise', 'price' => 999.0, 'is_active' => true],
            ['slug' => 'inactive-plan', 'name' => 'Inactive', 'price' => 0, 'is_active' => false],
        ] as $p) {
            Plan::query()->updateOrCreate(
                ['slug' => $p['slug']],
                array_merge($p, ['duration_days' => 30]),
            );
        }
    });

    /** @var Tenant $tenant */
    $tenant = tenant();
    tenancy()->central(function () use ($tenant): void {
        $plan = Plan::query()->where('slug', 'basico-mensual')->first();
        $tenant->subscription->update([
            'plan_id' => $plan->id,
            'stripe_id' => null,
        ]);
    });
    $tenant->refresh();
}

it('requires authentication', function (): void {
    seedPlanSwitchScaffold();

    $this->tenantPostJson('/api/v1/apps/plan', ['plan_slug' => 'pro-mensual'])
        ->assertUnauthorized();
});

it('returns 422 when plan_slug is missing', function (): void {
    $this->actingAsTenantUser();
    seedPlanSwitchScaffold();

    $this->tenantPostJson('/api/v1/apps/plan', [])
        ->assertStatus(422)
        ->assertJsonPath('errors.plan_slug.0', 'The plan slug field is required.');
});

it('returns 404 when the plan does not exist', function (): void {
    $this->actingAsTenantUser();
    seedPlanSwitchScaffold();

    $this->tenantPostJson('/api/v1/apps/plan', ['plan_slug' => 'ghost-plan'])
        ->assertNotFound()
        ->assertJsonPath('success', false);
});

it('returns 404 when the plan is inactive', function (): void {
    $this->actingAsTenantUser();
    seedPlanSwitchScaffold();

    $this->tenantPostJson('/api/v1/apps/plan', ['plan_slug' => 'inactive-plan'])
        ->assertNotFound();
});

it('rejects switching to the current plan', function (): void {
    $this->actingAsTenantUser();
    seedPlanSwitchScaffold();

    $this->tenantPostJson('/api/v1/apps/plan', ['plan_slug' => 'basico-mensual'])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Tenant is already on plan [basico-mensual].');
});

it('mirrors the plan switch to Stripe when a Stripe subscription is attached', function (): void {
    $this->actingAsTenantUser();
    seedPlanSwitchScaffold();

    /** @var Tenant $tenant */
    $tenant = tenant();
    tenancy()->central(function () use ($tenant): void {
        // stripe_id is not in $fillable (Cashier-managed); use forceFill.
        $tenant->subscription->forceFill(['stripe_id' => 'sub_test_123'])->save();
    });

    $this->tenantPostJson('/api/v1/apps/plan', ['plan_slug' => 'pro-mensual'])->assertOk();

    expect($this->fakeStripePlanService->calls)->toHaveCount(1)
        ->and($this->fakeStripePlanService->calls[0])->toMatchArray([
            'tenant_id' => $tenant->id,
            'old_slug' => 'basico-mensual',
            'new_slug' => 'pro-mensual',
        ]);
});

it('still calls the Stripe sync (no-op path) when no Stripe subscription is attached', function (): void {
    $this->actingAsTenantUser();
    seedPlanSwitchScaffold();

    $this->tenantPostJson('/api/v1/apps/plan', ['plan_slug' => 'pro-mensual'])->assertOk();

    // PlanService always invokes swapPlan when an old plan exists; the
    // service itself decides to no-op when there's no Stripe context.
    expect($this->fakeStripePlanService->calls)->toHaveCount(1);
});

it('aborts the local switch when Stripe sync throws', function (): void {
    $this->actingAsTenantUser();
    seedPlanSwitchScaffold();

    $this->app->instance(StripePlanService::class, new class extends StripePlanService
    {
        public function swapPlan(Tenant $tenant, Plan $oldPlan, Plan $newPlan): void
        {
            throw new RuntimeException('stripe boom');
        }
    });

    /** @var Tenant $tenant */
    $tenant = tenant();

    $this->tenantPostJson('/api/v1/apps/plan', ['plan_slug' => 'pro-mensual'])
        ->assertStatus(500);

    // Local plan must still be the old one.
    expect($tenant->fresh()->getPlanSlug())->toBe('basico-mensual');
});

it('switches the plan and returns the updated catalog', function (): void {
    $this->actingAsTenantUser();
    seedPlanSwitchScaffold();

    $response = $this->tenantPostJson('/api/v1/apps/plan', ['plan_slug' => 'pro-mensual'])
        ->assertOk()
        ->assertJsonPath('data.current_plan.slug', 'pro-mensual');

    $apps = collect($response->json('data.apps'))->keyBy('key');
    expect($apps['pos']['included_in_plan'])->toBeTrue()
        ->and($apps['pos']['enabled'])->toBeTrue()
        ->and($apps['loyalty']['included_in_plan'])->toBeTrue();

    /** @var Tenant $tenant */
    $tenant = tenant()->fresh();
    expect($tenant->getPlanSlug())->toBe('pro-mensual');
});

it('renews the subscription period from now', function (): void {
    $this->actingAsTenantUser();
    seedPlanSwitchScaffold();

    /** @var Tenant $tenant */
    $tenant = tenant();
    // Keep the sub valid (status=active, future ends_at) so the gate
    // middleware doesn't reject the request, but push starts_at into
    // the past so we can assert the renewal moved it forward.
    tenancy()->central(function () use ($tenant): void {
        $tenant->subscription->update([
            'starts_at' => now()->subYear(),
            'ends_at' => now()->addDay(),
        ]);
    });

    $this->tenantPostJson('/api/v1/apps/plan', ['plan_slug' => 'pro-mensual'])->assertOk();

    $sub = tenancy()->central(fn () => $tenant->subscription()->first());
    expect($sub->status)->toBe('active')
        ->and($sub->starts_at->diffInMinutes(now(), true))->toBeLessThan(2)
        ->and($sub->ends_at->diffInDays(now()->addDays(30), true))->toBeLessThan(1);
});

it('drops addons that the new plan bundles', function (): void {
    $this->actingAsTenantUser();
    seedPlanSwitchScaffold();

    /** @var Tenant $tenant */
    $tenant = tenant();
    tenancy()->central(function () use ($tenant): void {
        $tenant->addAddon('loyalty'); // becomes bundled in pro-mensual
        $tenant->addAddon('builder'); // not in pro-mensual, must remain
    });

    $this->tenantPostJson('/api/v1/apps/plan', ['plan_slug' => 'pro-mensual'])->assertOk();

    expect($tenant->fresh()->getAddonFeatures())
        ->toContain('builder')
        ->not->toContain('loyalty');
});

it('clears all addons when switching to a wildcard plan', function (): void {
    $this->actingAsTenantUser();
    seedPlanSwitchScaffold();

    /** @var Tenant $tenant */
    $tenant = tenant();
    tenancy()->central(function () use ($tenant): void {
        $tenant->addAddon('loyalty');
        $tenant->addAddon('builder');
    });

    $this->tenantPostJson('/api/v1/apps/plan', ['plan_slug' => 'enterprise-anual'])->assertOk();

    expect($tenant->fresh()->getAddonFeatures())->toBe([]);
});
