<?php

declare(strict_types=1);

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeSub(array $attrs): Subscription
{
    $plan = Plan::query()->firstOrCreate(
        ['slug' => 'sub-test-plan'],
        ['name' => 'X', 'price' => 0, 'duration_days' => 30, 'is_active' => true],
    );

    $tenant = Tenant::create(['id' => 'exp_'.bin2hex(random_bytes(4))]);

    $sub = Subscription::create(array_merge([
        'tenant_id' => $tenant->id,
        'plan_id' => $plan->id,
    ], $attrs));

    if (! empty($attrs['provider_id'])) {
        $sub->forceFill([
            'provider' => 'mercadopago',
            'provider_id' => $attrs['provider_id'],
        ])->save();
    }

    return $sub->fresh();
}

it('marks expired trials whose trial_ends_at has passed', function (): void {
    $expired = makeSub([
        'status' => 'trial',
        'trial_ends_at' => now()->subDay(),
        'starts_at' => now()->subDays(15),
        'ends_at' => now()->addDays(5),
    ]);

    $stillValid = makeSub([
        'status' => 'trial',
        'trial_ends_at' => now()->addDays(5),
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDays(20),
    ]);

    $this->artisan('subscriptions:expire')->assertSuccessful();

    expect($expired->fresh()->status)->toBe('expired');
    expect($stillValid->fresh()->status)->toBe('trial');
});

it('marks expired local active subs (no provider_id) without grace', function (): void {
    $expired = makeSub([
        'status' => 'active',
        'starts_at' => now()->subDays(31),
        'ends_at' => now()->subMinutes(10),
    ]);

    $this->artisan('subscriptions:expire')->assertSuccessful();

    expect($expired->fresh()->status)->toBe('expired');
});

it('respects grace window for provider-backed active subs', function (): void {
    $withinGrace = makeSub([
        'status' => 'active',
        'starts_at' => now()->subDays(30),
        'ends_at' => now()->subHours(2),
        'provider_id' => 'sub_within_grace',
    ]);

    $pastGrace = makeSub([
        'status' => 'active',
        'starts_at' => now()->subDays(30),
        'ends_at' => now()->subHours(48),
        'provider_id' => 'sub_past_grace',
    ]);

    $this->artisan('subscriptions:expire', ['--grace-hours' => 24])->assertSuccessful();

    expect($withinGrace->fresh()->status)->toBe('active');
    expect($pastGrace->fresh()->status)->toBe('expired');
});

it('finalizes period-end cancellations without provider grace time', function (): void {
    $cancelled = makeSub([
        'status' => 'active',
        'starts_at' => now()->subMonth(),
        'ends_at' => now()->subMinute(),
        'provider_id' => 'sub_cancel_at_period_end',
        'provider_status' => 'cancelled',
        'cancel_at_period_end' => true,
        'cancellation_requested_at' => now()->subWeek(),
    ]);

    $this->artisan('subscriptions:expire', ['--grace-hours' => 24])->assertSuccessful();

    expect($cancelled->fresh()->status)->toBe('cancelled');
});

it('makes no writes when --dry-run is set', function (): void {
    $expired = makeSub([
        'status' => 'trial',
        'trial_ends_at' => now()->subDay(),
        'starts_at' => now()->subDays(15),
        'ends_at' => now()->addDays(5),
    ]);

    $this->artisan('subscriptions:expire', ['--dry-run' => true])->assertSuccessful();

    expect($expired->fresh()->status)->toBe('trial');
});

it('returns success when there is nothing to expire', function (): void {
    makeSub([
        'status' => 'active',
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDays(15),
    ]);

    $this->artisan('subscriptions:expire')
        ->expectsOutputToContain('Nada que expirar')
        ->assertSuccessful();
});
