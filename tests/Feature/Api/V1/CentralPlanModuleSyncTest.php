<?php

declare(strict_types=1);

use App\Models\Module;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Covers the additions to PlanController/PlanResource for the central
 * plan→module mapping editor: store/update accept module_ids and
 * includes_all_modules; index/show return modules + module_ids.
 */
function asAdmin(): User
{
    $user = User::factory()->create(['role' => 'superadmin']);
    test()->actingAs($user, 'sanctum');

    return $user;
}

it('stores module_ids on the pivot when creating a plan', function (): void {
    asAdmin();
    $modules = Module::factory()->count(2)->sequence(
        ['key' => 'a'],
        ['key' => 'b'],
    )->create();

    $response = $this->postJson('/api/v1/plans', [
        'name' => 'New Plan',
        'slug' => 'new-plan',
        'price' => 19.99,
        'duration_days' => 30,
        'is_active' => true,
        'module_ids' => $modules->pluck('id')->all(),
    ]);

    $response->assertCreated();
    $plan = Plan::query()->where('slug', 'new-plan')->first();
    expect($plan->modules()->pluck('key')->all())
        ->toContain('a')
        ->toContain('b');
});

it('persists includes_all_modules flag on store', function (): void {
    asAdmin();

    $this->postJson('/api/v1/plans', [
        'name' => 'Wildcard',
        'slug' => 'wildcard',
        'price' => 99,
        'duration_days' => 30,
        'is_active' => true,
        'includes_all_modules' => true,
    ])->assertCreated();

    expect(Plan::query()->where('slug', 'wildcard')->first()->includes_all_modules)
        ->toBeTrue();
});

it('resyncs the pivot when module_ids is sent on update', function (): void {
    asAdmin();
    $plan = Plan::query()->create([
        'name' => 'P', 'slug' => 'p', 'price' => 0, 'duration_days' => 30, 'is_active' => true,
    ]);
    $a = Module::factory()->create(['key' => 'a']);
    $b = Module::factory()->create(['key' => 'b']);
    $plan->modules()->sync([$a->id]);

    $this->putJson("/api/v1/plans/{$plan->id}", [
        'name' => 'P',
        'slug' => 'p',
        'price' => 0,
        'duration_days' => 30,
        'module_ids' => [$b->id],
    ])->assertOk();

    expect($plan->fresh()->modules()->pluck('key')->all())
        ->toBe(['b']);
});

it('preserves the pivot when module_ids is omitted on update', function (): void {
    asAdmin();
    $plan = Plan::query()->create([
        'name' => 'P', 'slug' => 'p', 'price' => 0, 'duration_days' => 30, 'is_active' => true,
    ]);
    $a = Module::factory()->create(['key' => 'a']);
    $plan->modules()->sync([$a->id]);

    $this->putJson("/api/v1/plans/{$plan->id}", [
        'name' => 'P-renamed',
        'slug' => 'p',
        'price' => 1,
        'duration_days' => 30,
    ])->assertOk();

    expect($plan->fresh()->modules()->pluck('key')->all())->toBe(['a']);
});

it('exposes modules + module_ids in the plan resource', function (): void {
    asAdmin();
    $plan = Plan::query()->create([
        'name' => 'P', 'slug' => 'p', 'price' => 0, 'duration_days' => 30, 'is_active' => true,
    ]);
    $a = Module::factory()->create(['key' => 'a', 'label' => 'Alpha']);
    $plan->modules()->sync([$a->id]);

    $response = $this->getJson("/api/v1/plans/{$plan->id}");

    $response->assertOk()
        ->assertJsonPath('data.includes_all_modules', false)
        ->assertJsonPath('data.module_ids.0', $a->id)
        ->assertJsonPath('data.modules.0.key', 'a');
});

it('rejects module_ids that reference non-existent modules', function (): void {
    asAdmin();

    $this->postJson('/api/v1/plans', [
        'name' => 'P', 'slug' => 'p', 'price' => 0, 'duration_days' => 30,
        'module_ids' => [999999],
    ])->assertStatus(422)
      ->assertJsonValidationErrors(['module_ids.0']);
});
