<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\Tenant\V1\SiteNavItemRequest;
use App\Http\Requests\Api\Tenant\V1\SiteNavRequest;
use App\Http\Resources\SiteNavResource;
use App\Http\Resources\SiteNavItemResource;
use App\Models\Site;
use App\Models\SiteNav;
use App\Models\SiteNavItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class BuilderNavController extends ApiController
{
    public function index(): JsonResponse
    {
        $site = Site::query()->first();

        if (! $site) {
            return $this->notFound('No se encontró el sitio.');
        }

        $navs = $site->navs()->with('rootItems.children')->get();

        return $this->success(SiteNavResource::collection($navs));
    }

    public function show(string $location): JsonResponse
    {
        $site = Site::query()->first();

        if (! $site) {
            return $this->notFound('No se encontró el sitio.');
        }

        $nav = $site->navs()->where('location', $location)->with('rootItems.children')->first();

        if (! $nav) {
            return $this->notFound("Navegación '{$location}' no encontrada.");
        }

        return $this->success(new SiteNavResource($nav));
    }

    public function update(SiteNavRequest $request, string $location): JsonResponse
    {
        $site = Site::query()->first();

        if (! $site) {
            return $this->notFound('No se encontró el sitio.');
        }

        $nav = $site->navs()->where('location', $location)->first();

        if (! $nav) {
            $nav = $site->navs()->create([
                'location' => $location,
                'config' => $request->validated()['config'],
            ]);
        } else {
            $nav->update($request->validated());
        }

        $nav->load('rootItems.children');

        return $this->success(new SiteNavResource($nav));
    }

    public function storeItem(Request $request, string $location): JsonResponse
    {
        $site = Site::query()->first();
        $nav = $site?->navs()->where('location', $location)->first();

        if (! $nav) {
            return $this->notFound("Navegación '{$location}' no encontrada.");
        }

        $validated = app(SiteNavItemRequest::class)->validated();

        $maxSort = $nav->items()->max('sort_order') ?? -1;

        $item = $nav->items()->create(array_merge($validated, [
            'sort_order' => $validated['sort_order'] ?? $maxSort + 1,
        ]));

        return $this->created(new SiteNavItemResource($item));
    }

    public function updateItem(SiteNavItemRequest $request, string $location, SiteNavItem $item): JsonResponse
    {
        $item->update($request->validated());

        return $this->success(new SiteNavItemResource($item));
    }

    public function destroyItem(string $location, SiteNavItem $item): JsonResponse
    {
        $item->delete();

        return $this->noContent();
    }

    public function reorderItems(Request $request, string $location): JsonResponse
    {
        $site = Site::query()->first();
        $nav = $site?->navs()->where('location', $location)->first();

        if (! $nav) {
            return $this->notFound("Navegación '{$location}' no encontrada.");
        }

        $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        foreach ($request->input('ids') as $index => $id) {
            $nav->items()->where('id', $id)->update(['sort_order' => $index]);
        }

        $nav->load('rootItems.children');

        return $this->success(new SiteNavResource($nav));
    }
}
