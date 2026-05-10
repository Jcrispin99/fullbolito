<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\Tenant\V1\SitePageRequest;
use App\Http\Resources\SitePageResource;
use App\Http\Resources\SitePageVersionResource;
use App\Models\Site;
use App\Models\SitePage;
use App\Models\SitePageVersion;
use App\Models\SiteRedirect;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class BuilderPageController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $site = Site::query()->first();

        if (! $site) {
            return $this->notFound('No se encontró el sitio.');
        }

        $perPage = $request->input('per_page', 15);
        $search = $request->input('search');
        $status = $request->input('status');

        $query = $site->pages()->orderBy('is_homepage', 'desc')->orderBy('title');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        if ($status && in_array($status, ['draft', 'published', 'archived'])) {
            $query->where('status', $status);
        }

        if ($perPage === '-1' || $perPage === 'total') {
            return $this->success(SitePageResource::collection($query->get()));
        }

        $pages = $query->paginate((int) $perPage);

        return $this->success(
            SitePageResource::collection($pages)->response()->getData(true)
        );
    }

    public function store(SitePageRequest $request): JsonResponse
    {
        $site = Site::query()->first();

        if (! $site) {
            return $this->notFound('No se encontró el sitio.');
        }

        $data = $request->validated();
        $data['site_id'] = $site->id;

        if (! empty($data['is_homepage']) && $data['is_homepage']) {
            $site->pages()->where('is_homepage', true)->update(['is_homepage' => false]);
        }

        $page = SitePage::create($data);

        return $this->created(new SitePageResource($page));
    }

    public function show(SitePage $page): JsonResponse
    {
        $page->load('sections');

        return $this->success(new SitePageResource($page));
    }

    public function update(SitePageRequest $request, SitePage $page): JsonResponse
    {
        $data = $request->validated();
        $oldSlug = $page->slug;

        if (! empty($data['is_homepage']) && $data['is_homepage'] && ! $page->is_homepage) {
            SitePage::where('site_id', $page->site_id)
                ->where('id', '!=', $page->id)
                ->where('is_homepage', true)
                ->update(['is_homepage' => false]);
        }

        $page->update($data);

        // Auto-create redirect when slug changes
        if (isset($data['slug']) && $data['slug'] !== $oldSlug) {
            SiteRedirect::updateOrCreate(
                ['site_id' => $page->site_id, 'from_slug' => $oldSlug],
                ['to_slug' => $data['slug'], 'type' => 301],
            );
        }

        return $this->success(new SitePageResource($page));
    }

    public function destroy(SitePage $page): JsonResponse
    {
        $page->delete();

        return $this->noContent();
    }

    public function publish(Request $request, SitePage $page): JsonResponse
    {
        // Create version snapshot before publishing
        $page->load('sections');
        $lastVersion = $page->versions()->max('version_number') ?? 0;

        SitePageVersion::create([
            'page_id' => $page->id,
            'version_number' => $lastVersion + 1,
            'title' => $page->title,
            'slug' => $page->slug,
            'sections_snapshot' => $page->sections->map(fn ($s) => [
                'block_type_key' => $s->block_type_key,
                'sort_order' => $s->sort_order,
                'layout' => $s->layout,
                'content' => $s->content,
                'style_overrides' => $s->style_overrides,
                'is_visible' => $s->is_visible,
            ])->toArray(),
            'seo_snapshot' => [
                'seo_title' => $page->seo_title,
                'seo_description' => $page->seo_description,
                'og_image_asset_id' => $page->og_image_asset_id,
            ],
            'reason' => 'publish',
            'created_by' => $request->user()?->id,
        ]);

        $page->update([
            'status' => 'published',
            'published_at' => now(),
        ]);

        return $this->success(new SitePageResource($page));
    }

    public function unpublish(SitePage $page): JsonResponse
    {
        $page->update([
            'status' => 'draft',
        ]);

        return $this->success(new SitePageResource($page));
    }

    public function versions(SitePage $page): JsonResponse
    {
        $versions = $page->versions()
            ->orderByDesc('version_number')
            ->get();

        return $this->success(SitePageVersionResource::collection($versions));
    }

    public function revert(SitePage $page, SitePageVersion $version): JsonResponse
    {
        if ($version->page_id !== $page->id) {
            return $this->notFound('Versión no pertenece a esta página.');
        }

        // Delete current sections and recreate from snapshot
        $page->sections()->delete();

        foreach ($version->sections_snapshot as $sectionData) {
            $page->sections()->create($sectionData);
        }

        // Restore SEO data
        if ($version->seo_snapshot) {
            $page->update([
                'title' => $version->title,
                'seo_title' => $version->seo_snapshot['seo_title'] ?? null,
                'seo_description' => $version->seo_snapshot['seo_description'] ?? null,
                'og_image_asset_id' => $version->seo_snapshot['og_image_asset_id'] ?? null,
            ]);
        }

        $page->load('sections');

        return $this->success(new SitePageResource($page));
    }

    public function preview(SitePage $page): JsonResponse
    {
        $page->load(['sections' => fn ($q) => $q->ordered()]);

        return $this->success(new SitePageResource($page));
    }

    public function duplicate(SitePage $page): JsonResponse
    {
        $newPage = $page->replicate(['id', 'is_homepage', 'published_at']);
        $newPage->title = $page->title . ' (copia)';
        $newPage->slug = $page->slug . '-copia-' . time();
        $newPage->status = 'draft';
        $newPage->is_homepage = false;
        $newPage->save();

        foreach ($page->sections as $section) {
            $newSection = $section->replicate(['id']);
            $newSection->page_id = $newPage->id;
            $newSection->save();
        }

        $newPage->load('sections');

        return $this->created(new SitePageResource($newPage));
    }
}
