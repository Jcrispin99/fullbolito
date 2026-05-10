<?php

declare(strict_types=1);

use App\Listeners\HandleStripeWebhook;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Cashier\Events\WebhookReceived;

uses(RefreshDatabase::class);

/**
 * Tests for HandleStripeWebhook — the listener that mirrors Stripe state
 * (status, period dates, plan_id) onto our local subscriptions table.
 *
 * Each test seeds a tenant + subscription with a known stripe_id, then
 * dispatches a synthetic WebhookReceived payload. The listener resolves
 * the subscription via stripe_id and updates the relevant fields.
 */
function makeStripeSubscription(
    string $stripeId = 'sub_test_001',
    string $planSlug = 'basico-mensual',
    string $status = 'active',
): Subscription {
    $plan = Plan::query()->updateOrCreate(
        ['slug' => $planSlug],
        ['name' => ucfirst($planSlug), 'price' => 0, 'duration_days' => 30, 'is_active' => true],
    );

    $tenant = Tenant::create(['id' => 'wh_'.bin2hex(random_bytes(4))]);

    $sub = Subscription::create([
        'tenant_id' => $tenant->id,
        'plan_id' => $plan->id,
        'status' => $status,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
    ]);

    $sub->forceFill(['stripe_id' => $stripeId])->save();

    return $sub->fresh();
}

function dispatchWebhook(string $type, array $object, array $extra = []): void
{
    $payload = [
        'type' => $type,
        'data' => ['object' => $object],
    ] + $extra;

    (new HandleStripeWebhook())->handle(new WebhookReceived($payload));
}

it('updates status + period from customer.subscription.updated', function (): void {
    $sub = makeStripeSubscription();
    $newPeriodEnd = now()->addDays(30)->getTimestamp();
    $newPeriodStart = now()->getTimestamp();

    dispatchWebhook('customer.subscription.updated', [
        'id' => 'sub_test_001',
        'status' => 'active',
        'current_period_start' => $newPeriodStart,
        'current_period_end' => $newPeriodEnd,
        'items' => ['data' => []],
    ]);

    $sub->refresh();
    expect($sub->status)->toBe('active')
        ->and($sub->ends_at->getTimestamp())->toBe($newPeriodEnd)
        ->and($sub->starts_at->getTimestamp())->toBe($newPeriodStart);
});

it('rebinds plan_id when the price of a single-item subscription changes', function (): void {
    $sub = makeStripeSubscription();

    Plan::query()->updateOrCreate(
        ['slug' => 'pro-mensual'],
        ['name' => 'Pro', 'price' => 59.99, 'duration_days' => 30, 'is_active' => true, 'stripe_price_id' => 'price_pro_xxx'],
    );

    dispatchWebhook('customer.subscription.updated', [
        'id' => 'sub_test_001',
        'status' => 'active',
        'items' => [
            'data' => [
                ['price' => ['id' => 'price_pro_xxx']],
            ],
        ],
    ]);

    $sub->refresh()->load('plan');
    expect($sub->plan->slug)->toBe('pro-mensual');
});

it('does not rebind plan_id when subscription has multiple items (addon ambiguity)', function (): void {
    $sub = makeStripeSubscription();
    $originalPlanId = $sub->plan_id;

    Plan::query()->updateOrCreate(
        ['slug' => 'pro-mensual'],
        ['name' => 'Pro', 'price' => 59.99, 'duration_days' => 30, 'is_active' => true, 'stripe_price_id' => 'price_pro_xxx'],
    );

    dispatchWebhook('customer.subscription.updated', [
        'id' => 'sub_test_001',
        'status' => 'active',
        'items' => [
            'data' => [
                ['price' => ['id' => 'price_pro_xxx']],
                ['price' => ['id' => 'price_addon_yyy']],
            ],
        ],
    ]);

    expect($sub->fresh()->plan_id)->toBe($originalPlanId);
});

it('translates Stripe statuses to our internal vocabulary', function (): void {
    $sub = makeStripeSubscription();

    dispatchWebhook('customer.subscription.updated', [
        'id' => 'sub_test_001',
        'status' => 'past_due',
        'items' => ['data' => []],
    ]);
    expect($sub->fresh()->status)->toBe('past_due');

    dispatchWebhook('customer.subscription.updated', [
        'id' => 'sub_test_001',
        'status' => 'trialing',
        'items' => ['data' => []],
    ]);
    expect($sub->fresh()->status)->toBe('trial');
});

it('marks the subscription cancelled on customer.subscription.deleted', function (): void {
    $sub = makeStripeSubscription();

    dispatchWebhook('customer.subscription.deleted', [
        'id' => 'sub_test_001',
    ]);

    $sub->refresh();
    expect($sub->status)->toBe('cancelled')
        ->and($sub->ends_at->diffInMinutes(now(), true))->toBeLessThan(2);
});

it('re-activates and renews ends_at on invoice.payment_succeeded', function (): void {
    $sub = makeStripeSubscription(status: 'past_due');
    $newEnd = now()->addDays(30)->getTimestamp();

    dispatchWebhook('invoice.payment_succeeded', [
        'subscription' => 'sub_test_001',
        'lines' => [
            'data' => [
                ['period' => ['end' => $newEnd]],
            ],
        ],
    ]);

    $sub->refresh();
    expect($sub->status)->toBe('active')
        ->and($sub->ends_at->getTimestamp())->toBe($newEnd);
});

it('marks subscription past_due on invoice.payment_failed', function (): void {
    $sub = makeStripeSubscription();

    dispatchWebhook('invoice.payment_failed', [
        'subscription' => 'sub_test_001',
    ]);

    expect($sub->fresh()->status)->toBe('past_due');
});

it('ignores webhooks for unknown stripe_id without throwing', function (): void {
    expect(fn () => dispatchWebhook('customer.subscription.updated', [
        'id' => 'sub_does_not_exist',
        'status' => 'active',
        'items' => ['data' => []],
    ]))->not->toThrow(Throwable::class);
});

it('ignores unrelated event types', function (): void {
    $sub = makeStripeSubscription();

    dispatchWebhook('charge.succeeded', ['id' => 'ch_xxx']);

    // No fields changed.
    expect($sub->fresh()->status)->toBe($sub->status);
});
