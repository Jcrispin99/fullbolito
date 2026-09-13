<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant\V1;

use App\Http\Controllers\Api\ApiController;
use App\Jobs\SendInvoiceToSunatJob;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\MercadoPago\HandleMercadoPagoWebhook;
use App\Services\MercadoPago\MercadoPagoBillingService;
use App\Services\MercadoPago\MercadoPagoInvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

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
        $sunatCapacity = $tenant->getSunatCapacity();
        $dedicatedActive = $sunatCapacity['dedicated_queue']
            && (bool) config('saas.sunat.dedicated_queues_enabled', false);
        $pendingChange = \App\Models\SubscriptionPlanChange::query()
            ->with(['fromPlan', 'toPlan'])
            ->where('tenant_id', $tenant->id)
            ->whereIn('status', ['creating', 'pending_payment', 'paid', 'applying', 'scheduled', 'ready'])
            ->latest('id')
            ->first();
        /** @var Plan|null $fromPlan */
        $fromPlan = $pendingChange?->fromPlan;
        /** @var Plan|null $toPlan */
        $toPlan = $pendingChange?->toPlan;
        $tenantKey = $tenant->getTenantKey();
        $tenantKeyString = is_string($tenantKey) || is_int($tenantKey) ? (string) $tenantKey : '';

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
                'billing_rank' => (int) $plan->billing_rank,
                'sunat_worker_slots' => (int) $plan->sunat_worker_slots,
                'sunat_dedicated_queue' => (bool) $plan->sunat_dedicated_queue,
            ] : null,
            'pending_plan_change' => $pendingChange ? [
                'id' => $pendingChange->id,
                'kind' => $pendingChange->kind,
                'status' => $pendingChange->status,
                'from_plan' => $fromPlan?->name,
                'to_plan' => $toPlan?->name,
                'proration_amount' => (float) $pendingChange->proration_amount,
                'currency' => $pendingChange->currency,
                'effective_at' => $pendingChange->effective_at->toIso8601String(),
                'checkout_url' => $pendingChange->checkout_url,
            ] : null,
            'sunat_capacity' => [
                'worker_slots' => $sunatCapacity['worker_slots'],
                'dedicated_queue' => $sunatCapacity['dedicated_queue'],
                'dedicated_queue_active' => $dedicatedActive,
                'queue_name' => $dedicatedActive
                    ? SendInvoiceToSunatJob::dedicatedQueueName($tenantKeyString)
                    : 'sunat',
            ],
        ]);
    }

    public function plans(): JsonResponse
    {
        $plans = Plan::query()
            ->where('is_active', true)
            ->where('price', '>', 0)
            ->orderBy('price')
            ->get([
                'id',
                'name',
                'slug',
                'description',
                'price',
                'duration_days',
                'billing_rank',
                'sunat_worker_slots',
                'sunat_dedicated_queue',
            ]);

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

        try {
            $result = $billing->requestPlanChange(
                $tenant,
                $plan,
                mb_rtrim($this->appUrl(), '/').'/billing/success/'.$tenant->id,
            );
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success($result);
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
