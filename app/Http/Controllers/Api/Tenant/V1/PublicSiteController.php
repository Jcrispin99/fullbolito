<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\SiteNavResource;
use App\Http\Resources\SitePageResource;
use App\Http\Resources\SiteThemeResource;
use App\Models\Site;
use App\Models\SiteFormSubmission;
use App\Models\SitePage;
use App\Models\SiteRedirect;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PublicSiteController extends ApiController
{
    public function bootstrap(): JsonResponse
    {
        $site = Site::query()->with(['activeTheme', 'navs.rootItems.children'])->first();

        if (! $site) {
            return $this->notFound('Sitio no encontrado.');
        }

        $homepage = $site->pages()
            ->published()
            ->homepage()
            ->with('sections')
            ->first();

        if (! $homepage) {
            return $this->notFound('Página de inicio no encontrada.');
        }

        return $this->success([
            'site' => $this->siteData($site),
            'page' => new SitePageResource($homepage),
        ]);
    }

    public function site(): JsonResponse
    {
        $site = Site::query()->with(['activeTheme', 'navs.rootItems.children'])->first();

        if (! $site) {
            return $this->notFound('Sitio no encontrado.');
        }

        return $this->success($this->siteData($site));
    }

    public function pages(): JsonResponse
    {
        $site = Site::query()->first();

        if (! $site) {
            return $this->success([]);
        }

        $pages = $site->pages()
            ->published()
            ->orderBy('is_homepage', 'desc')
            ->orderBy('title')
            ->get(['id', 'title', 'slug', 'is_homepage']);

        return $this->success($pages);
    }

    public function page(string $slug): JsonResponse
    {
        $site = Site::query()->first();

        if (! $site) {
            return $this->notFound('Sitio no encontrado.');
        }

        $page = $site->pages()
            ->published()
            ->where('slug', $slug)
            ->with('sections')
            ->first();

        if (! $page) {
            // Check for redirect
            $redirect = SiteRedirect::where('site_id', $site->id)
                ->where('from_slug', $slug)
                ->first();

            if ($redirect) {
                return $this->success([
                    'redirect' => true,
                    'slug' => $redirect->to_slug,
                    'type' => $redirect->type,
                ]);
            }

            return $this->notFound('Página no encontrada.');
        }

        return $this->success(new SitePageResource($page));
    }

    public function submitForm(Request $request): JsonResponse
    {
        $site = Site::query()->first();

        if (! $site) {
            return $this->notFound('Sitio no encontrado.');
        }

        $request->validate([
            'page_id' => ['nullable', 'integer'],
            'section_id' => ['nullable', 'integer'],
            'form_type' => ['required', 'string', 'max:50'],
            'data' => ['required', 'array'],
        ]);

        $submission = SiteFormSubmission::create([
            'site_id' => $site->id,
            'page_id' => $request->input('page_id'),
            'section_id' => $request->input('section_id'),
            'form_type' => $request->input('form_type'),
            'data' => $request->input('data'),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return $this->created(['id' => $submission->id]);
    }

    /** @return array<string, mixed> */
    private function siteData(Site $site): array
    {
        return [
            'name' => $site->name,
            'status' => $site->status,
            'theme' => $site->activeTheme ? new SiteThemeResource($site->activeTheme) : null,
            'navs' => SiteNavResource::collection($site->navs),
        ];
    }
}
