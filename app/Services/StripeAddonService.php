<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Module;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Bridges addon toggles with Stripe subscription items.
 *
 * Stripe sync is best-effort: when the tenant has no active Stripe
 * subscription (common in dev/early-stage tenants), the service returns
 * silently after logging. This lets the local addon_features array stay
 * authoritative for feature gating while still keeping a hook for the
 * day production starts charging through Stripe.
 */
class StripeAddonService
{
    public function attach(Tenant $tenant, Module $module): void
    {
        $stripeSubscriptionId = $this->resolveStripeSubscriptionId($tenant);

        if ($stripeSubscriptionId === null || $module->stripe_price_id === null) {
            Log::info('StripeAddonService.attach skipped (no Stripe context)', [
                'tenant_id' => $tenant->id,
                'module' => $module->key,
                'has_stripe_subscription' => $stripeSubscriptionId !== null,
                'has_stripe_price' => $module->stripe_price_id !== null,
            ]);

            return;
        }

        try {
            $tenant->stripe()->subscriptionItems->create([
                'subscription' => $stripeSubscriptionId,
                'price' => $module->stripe_price_id,
                'quantity' => 1,
                'proration_behavior' => 'create_prorations',
            ]);
        } catch (Throwable $e) {
            Log::error('StripeAddonService.attach failed', [
                'tenant_id' => $tenant->id,
                'module' => $module->key,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function detach(Tenant $tenant, Module $module): void
    {
        $stripeSubscriptionId = $this->resolveStripeSubscriptionId($tenant);

        if ($stripeSubscriptionId === null || $module->stripe_price_id === null) {
            Log::info('StripeAddonService.detach skipped (no Stripe context)', [
                'tenant_id' => $tenant->id,
                'module' => $module->key,
            ]);

            return;
        }

        try {
            $items = $tenant->stripe()->subscriptionItems->all([
                'subscription' => $stripeSubscriptionId,
                'limit' => 100,
            ]);

            foreach ($items->data as $item) {
                if (($item->price->id ?? null) === $module->stripe_price_id) {
                    $tenant->stripe()->subscriptionItems->delete($item->id, [
                        'proration_behavior' => 'create_prorations',
                    ]);
                }
            }
        } catch (Throwable $e) {
            Log::error('StripeAddonService.detach failed', [
                'tenant_id' => $tenant->id,
                'module' => $module->key,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function resolveStripeSubscriptionId(Tenant $tenant): ?string
    {
        $subscription = $tenant->subscription()->first();

        return $subscription?->stripe_id;
    }
}
