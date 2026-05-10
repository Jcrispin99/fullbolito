<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\SiteBlockCatalogResource;
use App\Models\SiteBlockCatalog;
use Illuminate\Http\JsonResponse;

final class BuilderBlockTypeController extends ApiController
{
    public function index(): JsonResponse
    {
        $blocks = SiteBlockCatalog::query()
            ->active()
            ->ordered()
            ->get();

        return $this->success(SiteBlockCatalogResource::collection($blocks));
    }

    public function show(string $key): JsonResponse
    {
        $block = SiteBlockCatalog::where('key', $key)->first();

        if (! $block) {
            return $this->notFound("Tipo de bloque '{$key}' no encontrado.");
        }

        return $this->success(new SiteBlockCatalogResource($block));
    }
}
