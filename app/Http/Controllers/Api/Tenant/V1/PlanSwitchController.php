<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\MercadoPago\MercadoPagoBillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * POST /api/v1/apps/plan — switches the tenant to a different plan.
 *
 * Upgrades charge only the prorated difference. Downgrades are scheduled for
 * the end of the period already paid by the tenant.
 */
final class PlanSwitchController extends ApiController
{
    public function __construct(
        private readonly MercadoPagoBillingService $billing,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        /** @var Tenant|null $tenant */
        $tenant = tenant();

        if (! $tenant) {
            return $this->validationError(['tenant' => ['Tenant context required.']]);
        }

        try {
            /** @var array{plan_slug: string} $data */
            $data = $request->validate([
                'plan_slug' => ['required', 'string'],
            ]);
        } catch (ValidationException $e) {
            /** @var array<string, mixed> $errors */
            $errors = $e->errors();

            return $this->validationError($errors);
        }

        $plan = Plan::query()
            ->where('slug', $data['plan_slug'])
            ->where('is_active', true)
            ->first();

        if (! $plan) {
            return $this->notFound("Plan [{$data['plan_slug']}] not found.");
        }

        /** @var Subscription|null $current */
        $current = $tenant->subscription()->first();

        if ($current && (int) $current->plan_id === (int) $plan->id) {
            return $this->error("Tenant is already on plan [{$plan->slug}].", 422);
        }

        if ((float) $plan->price <= 0) {
            return $this->error('Los planes gratuitos no se pueden reactivar desde autoservicio.', 422);
        }

        try {
            $result = $this->billing->requestPlanChange(
                $tenant,
                $plan,
                mb_rtrim($this->appUrl(), '/').'/billing/success/'.$tenant->id,
            );
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success($result);
    }

    private function appUrl(): string
    {
        $url = config('app.url');

        return is_string($url) ? $url : 'http://localhost';
    }
}
