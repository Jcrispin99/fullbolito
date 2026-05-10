<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\Plan;
use App\Models\Tenant;
use App\Services\PlanService;
use App\Services\TenantAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * POST /api/v1/apps/plan — switches the tenant to a different plan.
 *
 * Delegates to {@see PlanService}, which mirrors the change to Stripe
 * when the tenant has a live Stripe subscription. A Stripe failure
 * aborts the whole switch (the local DB is never updated unless Stripe
 * accepted the new price).
 */
final class PlanSwitchController extends ApiController
{
    public function __construct(
        private readonly PlanService $planService,
        private readonly TenantAppService $catalog,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        /** @var Tenant|null $tenant */
        $tenant = tenant();

        if (! $tenant) {
            return $this->validationError(['tenant' => ['Tenant context required.']]);
        }

        try {
            $data = $request->validate([
                'plan_slug' => ['required', 'string'],
            ]);
        } catch (ValidationException $e) {
            return $this->validationError($e->errors());
        }

        $plan = Plan::query()
            ->where('slug', $data['plan_slug'])
            ->where('is_active', true)
            ->first();

        if (! $plan) {
            return $this->notFound("Plan [{$data['plan_slug']}] not found.");
        }

        $current = $tenant->subscription()->first();

        if ($current && (int) $current->plan_id === (int) $plan->id) {
            return $this->error("Tenant is already on plan [{$plan->slug}].", 422);
        }

        $tenant = $this->planService->switchPlan($tenant, $plan);

        return $this->success($this->catalog->getAppsData($tenant));
    }
}
