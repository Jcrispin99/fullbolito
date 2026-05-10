<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\Tenant\V1\PosCheckoutRequest;
use App\Services\PosCheckoutService;
use Illuminate\Http\JsonResponse;

final class PosCheckoutController extends ApiController
{
    public function store(PosCheckoutRequest $request, PosCheckoutService $checkoutService): JsonResponse
    {
        $result = $checkoutService->checkout($request->validated(), $request->user());

        return $this->created($result, 'POS checkout completed successfully');
    }
}
