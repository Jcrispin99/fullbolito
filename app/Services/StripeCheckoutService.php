<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Plan;
use App\Models\Tenant;

/**
 * Generates Stripe Checkout Sessions for tenant subscriptions.
 *
 * Wraps Cashier's newSubscription()->checkout(...) so the controller stays
 * thin and tests can swap this for a fake via the container.
 */
class StripeCheckoutService
{
    public function createSubscriptionCheckout(
        Tenant $tenant,
        Plan $plan,
        string $successUrl,
        string $cancelUrl,
    ): string {
        $checkout = $tenant->newSubscription('default', $plan->stripe_price_id)
            ->checkout([
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
            ]);

        return $checkout->url;
    }
}
