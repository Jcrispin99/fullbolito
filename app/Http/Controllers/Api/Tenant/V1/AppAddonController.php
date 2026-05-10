<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\Module;
use App\Models\Tenant;
use App\Services\StripeAddonService;
use App\Services\TenantAppService;
use Illuminate\Http\JsonResponse;

final class AppAddonController extends ApiController
{
    public function __construct(
        private readonly StripeAddonService $stripe,
        private readonly TenantAppService $catalog,
    ) {
    }

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

        if ($attach) {
            $this->stripe->attach($tenant, $module);
            $tenant->addAddon($key);
        } else {
            $this->stripe->detach($tenant, $module);
            $tenant->removeAddon($key);
        }

        return $this->success($this->catalog->getAppsData($tenant->fresh()));
    }
}
