<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Bridges in-app plan switches with Stripe.
 *
 * The local DB is authoritative for feature gating (see PlanService); this
 * service mirrors the change to Stripe so billing follows. It's best-effort:
 * when the tenant has no Stripe subscription, or either plan lacks a
 * stripe_price_id, the call is a logged no-op so dev/early-stage tenants
 * keep working without any Stripe data.
 *
 * The plan switch updates *only* the subscription item that matches the old
 * plan's price — addon items on the same Stripe subscription are left
 * untouched (that's what StripeAddonService manages).
 */
class StripePlanService
{
    public function swapPlan(Tenant $tenant, Plan $oldPlan, Plan $newPlan): void
    {
        $stripeSubscriptionId = $tenant->subscription()->first()?->stripe_id;

        if ($stripeSubscriptionId === null || $newPlan->stripe_price_id === null) {
            Log::info('StripePlanService.swapPlan skipped (no Stripe context)', [
                'tenant_id' => $tenant->id,
                'old_plan' => $oldPlan->slug,
                'new_plan' => $newPlan->slug,
                'has_stripe_subscription' => $stripeSubscriptionId !== null,
                'has_new_stripe_price' => $newPlan->stripe_price_id !== null,
            ]);

            return;
        }

        if ($oldPlan->stripe_price_id === null) {
            Log::warning('StripePlanService.swapPlan: old plan has no stripe_price_id; cannot locate item to update', [
                'tenant_id' => $tenant->id,
                'old_plan' => $oldPlan->slug,
            ]);

            return;
        }

        try {
            $items = $tenant->stripe()->subscriptionItems->all([
                'subscription' => $stripeSubscriptionId,
                'limit' => 100,
            ]);

            foreach ($items->data as $item) {
                if (($item->price->id ?? null) === $oldPlan->stripe_price_id) {
                    $tenant->stripe()->subscriptionItems->update($item->id, [
                        'price' => $newPlan->stripe_price_id,
                        'proration_behavior' => 'create_prorations',
                    ]);

                    return;
                }
            }

            Log::warning('StripePlanService.swapPlan: no matching subscription item found in Stripe', [
                'tenant_id' => $tenant->id,
                'old_plan_price' => $oldPlan->stripe_price_id,
            ]);
        } catch (Throwable $e) {
            Log::error('StripePlanService.swapPlan failed', [
                'tenant_id' => $tenant->id,
                'old_plan' => $oldPlan->slug,
                'new_plan' => $newPlan->slug,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
