<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\Plan;
use App\Models\Tenant;
use App\Services\StripeBillingPortalService;
use App\Services\StripeCheckoutService;
use App\Services\StripeInvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * Tenant-facing billing endpoints. Surfaces current subscription state,
 * generates Stripe Checkout URLs for upgrades, and Customer Portal URLs
 * for self-service billing management.
 */
final class BillingController extends ApiController
{
    public function show(): JsonResponse
    {
        /** @var Tenant|null $tenant */
        $tenant = tenant();

        if (! $tenant) {
            return $this->validationError(['tenant' => ['Tenant context required.']]);
        }

        $subscription = $tenant->subscription()->with('plan')->first();
        $plan = $subscription?->plan;

        return $this->success([
            'tenant_id' => $tenant->id,
            'has_stripe_customer' => $tenant->stripe_id !== null,
            'subscription' => $subscription ? [
                'status' => $subscription->status,
                'starts_at' => $subscription->starts_at?->toIso8601String(),
                'ends_at' => $subscription->ends_at?->toIso8601String(),
                'trial_ends_at' => $subscription->trial_ends_at?->toIso8601String(),
                'stripe_id' => $subscription->stripe_id,
            ] : null,
            'plan' => $plan ? [
                'id' => $plan->id,
                'name' => $plan->name,
                'slug' => $plan->slug,
                'price' => (float) $plan->price,
                'duration_days' => (int) $plan->duration_days,
            ] : null,
        ]);
    }

    public function plans(): JsonResponse
    {
        // Surface only billable plans — the SPA uses this to render
        // upgrade options, so non-Stripe / free plans would just be
        // dead options the user can't actually pick.
        $plans = Plan::query()
            ->where('is_active', true)
            ->whereNotNull('stripe_price_id')
            ->where('price', '>', 0)
            ->orderBy('price')
            ->get(['id', 'name', 'slug', 'description', 'price', 'duration_days', 'stripe_price_id']);

        return $this->success(['data' => $plans]);
    }

    public function checkout(Request $request, StripeCheckoutService $checkout): JsonResponse
    {
        /** @var Tenant|null $tenant */
        $tenant = tenant();

        if (! $tenant) {
            return $this->validationError(['tenant' => ['Tenant context required.']]);
        }

        $data = $request->validate([
            'plan_slug' => ['required', 'string'],
        ]);

        // Plans live in the central DB; the Plan model pins its connection,
        // so a plain query works from inside tenant context.
        $plan = Plan::query()
            ->where('slug', $data['plan_slug'])
            ->where('is_active', true)
            ->first();

        if (! $plan) {
            return $this->notFound("Plan [{$data['plan_slug']}] not found.");
        }

        if ($plan->stripe_price_id === null || (float) $plan->price <= 0) {
            return $this->error("Plan [{$data['plan_slug']}] is not billable via Stripe.", 422);
        }

        $url = $checkout->createSubscriptionCheckout(
            tenant: $tenant,
            plan: $plan,
            successUrl: URL::to('/billing/success?tenant=' . $tenant->id),
            cancelUrl: URL::to('/billing/cancel?tenant=' . $tenant->id),
        );

        return $this->success(['checkout_url' => $url]);
    }

    public function invoices(StripeInvoiceService $invoices): JsonResponse
    {
        /** @var Tenant|null $tenant */
        $tenant = tenant();

        if (! $tenant) {
            return $this->validationError(['tenant' => ['Tenant context required.']]);
        }

        return $this->success(['data' => $invoices->listInvoices($tenant)]);
    }

    public function portal(StripeBillingPortalService $portal): JsonResponse
    {
        /** @var Tenant|null $tenant */
        $tenant = tenant();

        if (! $tenant) {
            return $this->validationError(['tenant' => ['Tenant context required.']]);
        }

        if ($tenant->stripe_id === null) {
            return $this->error('No Stripe customer linked to this tenant.', 422);
        }

        $url = $portal->getPortalUrl($tenant, URL::to('/billing'));

        return $this->success(['portal_url' => $url]);
    }
}
