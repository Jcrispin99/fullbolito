<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Events\WebhookReceived;

/**
 * Mirrors Stripe-side subscription state changes onto our local
 * `subscriptions` table so feature gating + the in-app dashboard stay
 * in sync with what Stripe is actually billing.
 *
 * Cashier's built-in WebhookController already updates the columns it
 * knows about (stripe_status, ends_at, trial_ends_at). This listener
 * fills in *our* domain columns: `status`, `starts_at`, `plan_id`.
 *
 * The mapping is intentionally lenient — when we receive a payload for
 * a subscription we don't have locally, we log + ignore (Cashier's own
 * handler will create it from `customer.subscription.created`).
 */
final class HandleStripeWebhook
{
    public function handle(WebhookReceived $event): void
    {
        $payload = $event->payload;
        $type = $payload['type'] ?? null;

        match ($type) {
            'customer.subscription.created',
            'customer.subscription.updated' => $this->onSubscriptionUpdated($payload),
            'customer.subscription.deleted' => $this->onSubscriptionDeleted($payload),
            'invoice.payment_succeeded' => $this->onInvoicePaid($payload),
            'invoice.payment_failed' => $this->onInvoiceFailed($payload),
            default => null,
        };
    }

    private function onSubscriptionUpdated(array $payload): void
    {
        $data = $payload['data']['object'] ?? [];
        $stripeId = $data['id'] ?? null;

        if (! $stripeId) {
            return;
        }

        $subscription = Subscription::query()->where('stripe_id', $stripeId)->first();

        if (! $subscription) {
            Log::info('HandleStripeWebhook: subscription.updated for unknown stripe_id', [
                'stripe_id' => $stripeId,
            ]);

            return;
        }

        $updates = [
            'status' => $this->mapStripeStatus($data['status'] ?? null),
        ];

        if (! empty($data['current_period_end'])) {
            $updates['ends_at'] = Carbon::createFromTimestamp((int) $data['current_period_end']);
        }

        if (! empty($data['current_period_start'])) {
            $updates['starts_at'] = Carbon::createFromTimestamp((int) $data['current_period_start']);
        }

        // If the price on the (single) plan item changed, re-bind plan_id.
        $newPriceId = $this->extractPlanPriceId($data);
        if ($newPriceId) {
            $newPlan = Plan::query()->where('stripe_price_id', $newPriceId)->first();
            if ($newPlan && (int) $newPlan->id !== (int) $subscription->plan_id) {
                $updates['plan_id'] = $newPlan->id;
            }
        }

        $subscription->update($updates);
    }

    private function onSubscriptionDeleted(array $payload): void
    {
        $stripeId = $payload['data']['object']['id'] ?? null;

        if (! $stripeId) {
            return;
        }

        Subscription::query()
            ->where('stripe_id', $stripeId)
            ->update([
                'status' => 'cancelled',
                'ends_at' => Carbon::now(),
            ]);
    }

    private function onInvoicePaid(array $payload): void
    {
        $stripeId = $payload['data']['object']['subscription'] ?? null;

        if (! $stripeId) {
            return;
        }

        $subscription = Subscription::query()->where('stripe_id', $stripeId)->first();

        if (! $subscription) {
            return;
        }

        $updates = ['status' => 'active'];

        $periodEnd = $payload['data']['object']['lines']['data'][0]['period']['end'] ?? null;
        if ($periodEnd) {
            $updates['ends_at'] = Carbon::createFromTimestamp((int) $periodEnd);
        }

        $subscription->update($updates);
    }

    private function onInvoiceFailed(array $payload): void
    {
        $stripeId = $payload['data']['object']['subscription'] ?? null;

        if (! $stripeId) {
            return;
        }

        Subscription::query()
            ->where('stripe_id', $stripeId)
            ->update(['status' => 'past_due']);
    }

    /**
     * Translate Stripe's status into our internal vocabulary.
     *
     * Stripe statuses: incomplete, incomplete_expired, trialing, active,
     * past_due, canceled, unpaid, paused.
     */
    private function mapStripeStatus(?string $stripeStatus): string
    {
        return match ($stripeStatus) {
            'active' => 'active',
            'trialing' => 'trial',
            'past_due', 'unpaid' => 'past_due',
            'canceled', 'incomplete_expired' => 'cancelled',
            'paused' => 'paused',
            default => 'active',
        };
    }

    /**
     * Extract the price id of the *plan* item. We can only confidently
     * identify it when the subscription has a single item; addon items
     * make this ambiguous so we fall back to "no change".
     */
    private function extractPlanPriceId(array $data): ?string
    {
        $items = $data['items']['data'] ?? [];

        if (count($items) !== 1) {
            return null;
        }

        return $items[0]['price']['id'] ?? null;
    }
}
