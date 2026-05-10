<?php

declare(strict_types=1);

use App\Models\Module;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function actingAsSuperadmin(): User
{
    $user = User::factory()->create(['role' => 'superadmin']);
    test()->actingAs($user, 'sanctum');

    return $user;
}

function actingAsRegular(): User
{
    $user = User::factory()->create(['role' => 'user']);
    test()->actingAs($user, 'sanctum');

    return $user;
}

it('lists modules for any authenticated user', function (): void {
    Module::factory()->count(3)->create();
    actingAsRegular();

    $response = $this->getJson('/api/v1/modules');

    $response->assertOk()
        ->assertJsonStructure(['data' => ['data', 'meta']]);
});

it('creates a module when the user is superadmin', function (): void {
    actingAsSuperadmin();

    $response = $this->postJson('/api/v1/modules', [
        'key' => 'new_module',
        'label' => 'Nuevo Módulo',
        'addon_price' => 4.99,
        'is_active' => true,
        'sort_order' => 100,
    ]);

    $response->assertCreated();
    $this->assertDatabaseHas('modules', ['key' => 'new_module', 'addon_price' => '4.99']);
});

it('rejects module creation for non-superadmin users', function (): void {
    actingAsRegular();

    $response = $this->postJson('/api/v1/modules', [
        'key' => 'forbidden',
        'label' => 'Forbidden',
    ]);

    $response->assertForbidden();
    $this->assertDatabaseMissing('modules', ['key' => 'forbidden']);
});

it('rejects invalid module keys', function (): void {
    actingAsSuperadmin();

    $response = $this->postJson('/api/v1/modules', [
        'key' => 'Invalid Key!',
        'label' => 'X',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['key']);
});

it('updates a module', function (): void {
    actingAsSuperadmin();
    $module = Module::factory()->create(['key' => 'editable', 'label' => 'Original']);

    $response = $this->putJson("/api/v1/modules/{$module->id}", [
        'key' => 'editable',
        'label' => 'Renombrado',
    ]);

    $response->assertOk();
    expect($module->fresh()->label)->toBe('Renombrado');
});

it('toggles module status', function (): void {
    actingAsSuperadmin();
    $module = Module::factory()->create(['is_active' => true]);

    $this->patchJson("/api/v1/modules/{$module->id}/toggle-status")->assertOk();

    expect($module->fresh()->is_active)->toBeFalse();
});

it('deactivates instead of deleting when the module is bound to a plan', function (): void {
    actingAsSuperadmin();

    $plan = Plan::query()->create([
        'name' => 'P', 'slug' => 'p', 'price' => 0, 'duration_days' => 30, 'is_active' => true,
    ]);
    $module = Module::factory()->create();
    $plan->modules()->attach($module->id);

    $this->deleteJson("/api/v1/modules/{$module->id}")->assertOk();

    // Bound to a plan → soft "delete" via deactivation (preserves tenant access).
    $this->assertDatabaseHas('modules', ['id' => $module->id, 'is_active' => false]);
});

it('hard-deletes orphan modules', function (): void {
    actingAsSuperadmin();
    $module = Module::factory()->create();

    $this->deleteJson("/api/v1/modules/{$module->id}")->assertOk();

    $this->assertDatabaseMissing('modules', ['id' => $module->id]);
});
