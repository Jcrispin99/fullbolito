<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\Tenant;
use App\Services\TenantAppService;
use Illuminate\Http\JsonResponse;

final class TenantAppController extends ApiController
{
    public function __construct(private readonly TenantAppService $service)
    {
    }

    public function index(): JsonResponse
    {
        /** @var Tenant|null $tenant */
        $tenant = tenant();

        if (! $tenant) {
            return $this->validationError(['tenant' => ['Tenant context required.']]);
        }

        return $this->success($this->service->getAppsData($tenant));
    }
}
