<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\MercadoPago\HandleMercadoPagoWebhook;
use App\Services\MercadoPago\MercadoPagoBillingService;
use App\Services\MercadoPago\MercadoPagoInvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tenant-facing billing endpoints. Surfaces current subscription state,
 * creates Mercado Pago subscription checkouts and exposes payment history.
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

        /** @var Subscription|null $subscription */
        $subscription = $tenant->subscription()->with('plan')->first();
        /** @var Plan|null $plan */
        $plan = $subscription?->plan;

        return $this->success([
            'tenant_id' => $tenant->id,
            'billing_provider' => $tenant->billing_provider,
            'has_payment_subscription' => $subscription?->provider === 'mercadopago'
                && $subscription->provider_id !== null,
            'subscription' => $subscription ? [
                'status' => $subscription->status,
                'starts_at' => $subscription->starts_at?->toIso8601String(),
                'ends_at' => $subscription->ends_at?->toIso8601String(),
                'trial_ends_at' => $subscription->trial_ends_at?->toIso8601String(),
                'provider' => $subscription->provider,
                'provider_id' => $subscription->provider_id,
                'provider_status' => $subscription->provider_status,
                'next_billing_at' => $subscription->next_billing_at?->toIso8601String(),
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
        $plans = Plan::query()
            ->where('is_active', true)
            ->where('price', '>', 0)
            ->orderBy('price')
            ->get(['id', 'name', 'slug', 'description', 'price', 'duration_days']);

        return $this->success(['data' => $plans]);
    }

    public function checkout(Request $request, MercadoPagoBillingService $billing): JsonResponse
    {
        /** @var Tenant|null $tenant */
        $tenant = tenant();

        if (! $tenant) {
            return $this->validationError(['tenant' => ['Tenant context required.']]);
        }

        /** @var array{plan_slug: string} $data */
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

        if ((float) $plan->price <= 0) {
            return $this->error("El plan [{$data['plan_slug']}] no requiere un checkout.", 422);
        }

        $url = $billing->createSubscriptionCheckout(
            $tenant,
            $plan,
            mb_rtrim($this->appUrl(), '/').'/billing/success?tenant='.$tenant->id,
        );

        return $this->success(['checkout_url' => $url]);
    }

    public function invoices(MercadoPagoInvoiceService $invoices): JsonResponse
    {
        /** @var Tenant|null $tenant */
        $tenant = tenant();

        if (! $tenant) {
            return $this->validationError(['tenant' => ['Tenant context required.']]);
        }

        return $this->success(['data' => $invoices->listPayments($tenant)]);
    }

    public function updateSubscriptionStatus(
        Request $request,
        MercadoPagoBillingService $billing,
        HandleMercadoPagoWebhook $webhooks,
    ): JsonResponse {
        /** @var Tenant|null $tenant */
        $tenant = tenant();

        if (! $tenant) {
            return $this->validationError(['tenant' => ['Tenant context required.']]);
        }

        /** @var array{status: string} $data */
        $data = $request->validate([
            'status' => ['required', 'string', 'in:authorized,paused,cancelled'],
        ]);

        $remote = $billing->changeStatus($tenant, $data['status']);
        $webhooks->syncPreapproval($remote);

        return $this->success([
            'status' => $remote['status'] ?? $data['status'],
        ]);
    }

    private function appUrl(): string
    {
        $url = config('app.url');

        return is_string($url) ? $url : 'http://localhost';
    }
}
