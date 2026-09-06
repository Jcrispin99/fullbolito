<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\Module;
use App\Models\Tenant;
use App\Services\MercadoPago\MercadoPagoBillingService;
use App\Services\TenantAppService;
use Illuminate\Http\JsonResponse;

final class AppAddonController extends ApiController
{
    public function __construct(
        private readonly MercadoPagoBillingService $billing,
        private readonly TenantAppService $catalog,
    ) {}

    public function attach(string $key): JsonResponse
    {
        return $this->toggle($key, attach: true);
    }

    public function detach(string $key): JsonResponse
    {
        return $this->toggle($key, attach: false);
    }

    private function toggle(string $key, bool $attach): JsonResponse
    {
        /** @var Tenant|null $tenant */
        $tenant = tenant();

        if (! $tenant) {
            return $this->validationError(['tenant' => ['Tenant context required.']]);
        }

        $module = Module::query()->where('key', $key)->active()->first();

        if (! $module) {
            return $this->notFound("Module [{$key}] not found.");
        }

        if (! $module->isAddon()) {
            return $this->error("Module [{$key}] is not an addon.", 422);
        }

        if ($tenant->isFeatureFromPlan($key)) {
            return $this->error("Module [{$key}] is included in the current plan.", 422);
        }

        if (! $this->billing->hasActiveSubscription($tenant)) {
            return $this->error('Necesitas una suscripción activa de Mercado Pago para modificar addons pagos.', 422);
        }

        $addons = $tenant->getAddonFeatures();
        if ($attach) {
            if (! in_array($key, $addons, true)) {
                $addons[] = $key;
            }
            $this->billing->updateForAddons($tenant, array_values($addons));
            $tenant->addAddon($key);
        } else {
            $addons = array_values(array_filter($addons, fn (string $addon) => $addon !== $key));
            $this->billing->updateForAddons($tenant, $addons);
            $tenant->removeAddon($key);
        }

        $freshTenant = $tenant->fresh();

        return $this->success($this->catalog->getAppsData($freshTenant ?? $tenant));
    }
}
