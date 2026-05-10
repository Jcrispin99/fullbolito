<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\SiteRedirectResource;
use App\Models\Site;
use App\Models\SiteRedirect;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class BuilderRedirectController extends ApiController
{
    public function index(): JsonResponse
    {
        $site = Site::query()->first();

        if (! $site) {
            return $this->notFound('No se encontró el sitio.');
        }

        $redirects = $site->redirects()->orderByDesc('created_at')->get();

        return $this->success(SiteRedirectResource::collection($redirects));
    }

    public function store(Request $request): JsonResponse
    {
        $site = Site::query()->first();

        if (! $site) {
            return $this->notFound('No se encontró el sitio.');
        }

        $data = $request->validate([
            'from_slug' => ['required', 'string', 'max:255'],
            'to_slug' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'integer', 'in:301,302'],
        ]);

        $data['site_id'] = $site->id;
        $data['type'] = $data['type'] ?? 301;

        $redirect = SiteRedirect::updateOrCreate(
            ['site_id' => $site->id, 'from_slug' => $data['from_slug']],
            ['to_slug' => $data['to_slug'], 'type' => $data['type']],
        );

        return $this->created(new SiteRedirectResource($redirect));
    }

    public function show(SiteRedirect $redirect): JsonResponse
    {
        return $this->success(new SiteRedirectResource($redirect));
    }

    public function update(Request $request, SiteRedirect $redirect): JsonResponse
    {
        $data = $request->validate([
            'from_slug' => ['sometimes', 'string', 'max:255'],
            'to_slug' => ['sometimes', 'string', 'max:255'],
            'type' => ['nullable', 'integer', 'in:301,302'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $redirect->update($data);

        return $this->success(new SiteRedirectResource($redirect));
    }

    public function destroy(SiteRedirect $redirect): JsonResponse
    {
        $redirect->delete();

        return $this->noContent();
    }
}
