<?php

declare(strict_types=1);

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function makeTenantWithSub(string $idPrefix, string $planSlug, string $status, float $price = 59.99, int $days = 30): void
{
    $id = $idPrefix . '-' . bin2hex(random_bytes(4));

    $plan = Plan::query()->firstOrCreate(
        ['slug' => $planSlug],
        ['name' => ucfirst($planSlug), 'price' => $price, 'duration_days' => $days, 'is_active' => true],
    );

    $tenant = Tenant::query()->create([
        'id' => $id,
        'business_name' => "Biz {$id}",
        'owner_email' => "{$id}@example.test",
    ]);

    Subscription::query()->create([
        'tenant_id' => $tenant->id,
        'plan_id' => $plan->id,
        'status' => $status,
        'starts_at' => now(),
        'ends_at' => now()->addDays($days),
    ]);
}

beforeEach(function (): void {
    $this->superadmin = User::query()->create([
        'name' => 'Admin', 'email' => 'admin@x.test', 'password' => bcrypt('secret'), 'role' => 'superadmin',
    ]);
    $this->normalUser = User::query()->create([
        'name' => 'Normal', 'email' => 'normal@x.test', 'password' => bcrypt('secret'), 'role' => 'user',
    ]);
});

it('forbids non-superadmin', function (): void {
    Sanctum::actingAs($this->normalUser);

    $this->getJson('/api/v1/subscriptions')->assertForbidden();
});

it('returns paginated list with summary for superadmin', function (): void {
    makeTenantWithSub('biz-a', 'pro-mensual', 'active', 59.99, 30);
    makeTenantWithSub('biz-b', 'pro-mensual', 'active', 59.99, 30);
    makeTenantWithSub('biz-c', 'free-trial', 'trial', 0, 14);
    makeTenantWithSub('biz-d', 'enterprise-anual', 'active', 999.99, 365);

    Sanctum::actingAs($this->superadmin);

    $response = $this->getJson('/api/v1/subscriptions')->assertOk();

    expect($response->json('data.summary.active_count'))->toBe(3);
    expect($response->json('data.summary.trial_count'))->toBe(1);
    // MRR only counts subscriptions with duration_days <= 31 (monthly).
    // 2 x 59.99 = 119.98. Enterprise-anual is excluded (365 days).
    expect((float) $response->json('data.summary.monthly_recurring_revenue'))
        ->toEqualWithDelta(119.98, 0.01);

    expect($response->json('data.meta.total'))->toBe(4);
});

it('filters by status when query param provided', function (): void {
    makeTenantWithSub('biz-1', 'pro-mensual', 'active');
    makeTenantWithSub('biz-2', 'free-trial', 'trial', 0, 14);

    Sanctum::actingAs($this->superadmin);

    $response = $this->getJson('/api/v1/subscriptions?status=trial')->assertOk();

    expect($response->json('data.meta.total'))->toBe(1);
    expect($response->json('data.data.0.status'))->toBe('trial');
});
