<?php

declare(strict_types=1);

use App\Models\Module;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\Http;

/**
 * Verifies POST/DELETE /api/v1/apps/{key}/addon:
 *  - requires authentication
 *  - rejects non-existent or non-addon modules
 *  - rejects modules already included in the current plan
 *  - successfully toggles the tenant's addon_features array
 *  - returns the refreshed catalog so the SPA can re-render
 */
function seedAddonScaffold(): void
{
    // Prod basico-mensual uses '*' (all modules) which would mark loyalty
    // as included-in-plan and block the addon flow. Pin a deterministic list.
    config()->set('saas.plans.basico-mensual.features', ['sales']);

    tenancy()->central(function (): void {
        collect([
            ['key' => 'sales',   'label' => 'Ventas',      'addon_price' => 0,    'sort_order' => 10],
            ['key' => 'loyalty', 'label' => 'Fidelización', 'addon_price' => 9.99, 'sort_order' => 20],
            ['key' => 'builder', 'label' => 'Builder',     'addon_price' => 14.99, 'sort_order' => 30],
        ])->each(fn (array $row) => Module::query()->updateOrCreate(
            ['key' => $row['key']],
            array_merge(['is_active' => true, 'description' => null, 'icon' => null], $row),
        ));

        Plan::query()->updateOrCreate(
            ['slug' => 'basico-mensual'],
            ['name' => 'Básico', 'price' => 29.99, 'duration_days' => 30, 'is_active' => true],
        );
    });

    /** @var App\Models\Tenant $tenant */
    $tenant = tenant();
    tenancy()->central(function () use ($tenant): void {
        $plan = Plan::query()->where('slug', 'basico-mensual')->first();
        $tenant->owner_email = 'owner@example.test';
        $tenant->save();
        $tenant->subscription->update([
            'plan_id' => $plan->id,
            'provider' => 'mercadopago',
            'provider_id' => 'preapproval-addon-test',
            'provider_status' => 'authorized',
            'external_reference' => (string) Illuminate\Support\Str::uuid(),
        ]);
    });
    $tenant->refresh();
}

beforeEach(function (): void {
    config()->set('mercadopago.access_token', 'TEST-token');
    Http::fake([
        'api.mercadopago.com/preapproval/preapproval-addon-test' => Http::response([
            'id' => 'preapproval-addon-test',
            'status' => 'authorized',
        ]),
    ]);
});

it('requires authentication for attach', function (): void {
    seedAddonScaffold();

    $this->tenantPostJson('/api/v1/apps/loyalty/addon')->assertUnauthorized();
});

it('requires authentication for detach', function (): void {
    seedAddonScaffold();

    $this->tenantDeleteJson('/api/v1/apps/loyalty/addon')->assertUnauthorized();
});

it('returns 404 when the module does not exist', function (): void {
    $this->actingAsTenantUser(User::factory()->create(['email' => 'owner@example.test']));
    seedAddonScaffold();

    $this->tenantPostJson('/api/v1/apps/ghost/addon')
        ->assertNotFound()
        ->assertJsonPath('success', false);
});

it('rejects modules that are not addons', function (): void {
    $this->actingAsTenantUser(User::factory()->create(['email' => 'owner@example.test']));
    seedAddonScaffold();

    $this->tenantPostJson('/api/v1/apps/sales/addon')
        ->assertStatus(422)
        ->assertJsonPath('message', 'Module [sales] is not an addon.');
});

it('rejects addons already included in the plan', function (): void {
    $this->actingAsTenantUser(User::factory()->create(['email' => 'owner@example.test']));
    seedAddonScaffold();

    // Force the plan to include `loyalty` so it's no longer an upgrade.
    config()->set('saas.plans.basico-mensual.features', ['sales', 'loyalty']);

    $this->tenantPostJson('/api/v1/apps/loyalty/addon')
        ->assertStatus(422)
        ->assertJsonPath('message', 'Module [loyalty] is included in the current plan.');
});

it('attaches an addon and returns the updated catalog', function (): void {
    $this->actingAsTenantUser(User::factory()->create(['email' => 'owner@example.test']));
    seedAddonScaffold();

    $response = $this->tenantPostJson('/api/v1/apps/loyalty/addon')->assertOk();

    /** @var App\Models\Tenant $tenant */
    $tenant = tenant()->fresh();
    expect($tenant->getAddonFeatures())->toContain('loyalty');

    $apps = collect($response->json('data.apps'))->keyBy('key');
    expect($apps['loyalty']['is_active_addon'])->toBeTrue()
        ->and($apps['loyalty']['enabled'])->toBeTrue();

    expect($response->json('data.addon_total'))->toBe(9.99);
});

it('detaches an addon and returns the updated catalog', function (): void {
    $this->actingAsTenantUser(User::factory()->create(['email' => 'owner@example.test']));
    seedAddonScaffold();

    /** @var App\Models\Tenant $tenant */
    $tenant = tenant();
    tenancy()->central(fn () => $tenant->addAddon('loyalty'));

    $response = $this->tenantDeleteJson('/api/v1/apps/loyalty/addon')->assertOk();

    expect($tenant->fresh()->getAddonFeatures())->not->toContain('loyalty');

    $apps = collect($response->json('data.apps'))->keyBy('key');
    expect($apps['loyalty']['is_active_addon'])->toBeFalse()
        ->and($apps['loyalty']['enabled'])->toBeFalse();
});
