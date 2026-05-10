<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\SiteSectionResource;
use App\Models\Site;
use App\Models\SiteBlockCatalog;
use App\Models\SiteSection;
use App\Services\BlockSchemaValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class BuilderGlobalSectionController extends ApiController
{
    public function __construct(
        private readonly BlockSchemaValidator $validator,
    ) {}

    public function index(): JsonResponse
    {
        $site = Site::query()->first();

        if (! $site) {
            return $this->notFound('No se encontró el sitio.');
        }

        $globals = SiteSection::where('site_id', $site->id)
            ->global()
            ->ordered()
            ->get();

        return $this->success(SiteSectionResource::collection($globals));
    }

    public function store(Request $request): JsonResponse
    {
        $site = Site::query()->first();

        if (! $site) {
            return $this->notFound('No se encontró el sitio.');
        }

        $data = $request->validate([
            'block_type_key' => ['required', 'string'],
            'global_name' => ['required', 'string', 'max:100'],
            'layout' => ['nullable', 'array'],
            'content' => ['nullable', 'array'],
            'style_overrides' => ['nullable', 'array'],
        ]);

        $catalog = SiteBlockCatalog::where('key', $data['block_type_key'])->first();

        if (! $catalog) {
            return $this->error("Tipo de bloque '{$data['block_type_key']}' no existe.", 422);
        }

        $content = $data['content'] ?? $catalog->default_content;
        $layout = $data['layout'] ?? $catalog->default_layout;

        $validation = $this->validator->validate($data['block_type_key'], $content, $data['style_overrides'] ?? []);

        if (! $validation['valid']) {
            return $this->validationError($validation['errors']);
        }

        $section = SiteSection::create([
            'site_id' => $site->id,
            'page_id' => null,
            'block_type_key' => $data['block_type_key'],
            'sort_order' => 0,
            'layout' => $layout,
            'content' => $content,
            'style_overrides' => $data['style_overrides'] ?? null,
            'is_visible' => true,
            'is_global' => true,
            'global_name' => $data['global_name'],
        ]);

        return $this->created(new SiteSectionResource($section));
    }

    public function show(SiteSection $section): JsonResponse
    {
        if (! $section->is_global) {
            return $this->notFound('Sección global no encontrada.');
        }

        return $this->success(new SiteSectionResource($section));
    }

    public function update(Request $request, SiteSection $section): JsonResponse
    {
        if (! $section->is_global) {
            return $this->notFound('Sección global no encontrada.');
        }

        $data = $request->validate([
            'global_name' => ['sometimes', 'string', 'max:100'],
            'layout' => ['nullable', 'array'],
            'content' => ['nullable', 'array'],
            'style_overrides' => ['nullable', 'array'],
        ]);

        $content = $data['content'] ?? $section->content;
        $styleOverrides = array_key_exists('style_overrides', $data) ? $data['style_overrides'] : $section->style_overrides;

        $validation = $this->validator->validate($section->block_type_key, $content, $styleOverrides ?? []);

        if (! $validation['valid']) {
            return $this->validationError($validation['errors']);
        }

        $section->update($data);

        return $this->success(new SiteSectionResource($section));
    }

    public function destroy(SiteSection $section): JsonResponse
    {
        if (! $section->is_global) {
            return $this->notFound('Sección global no encontrada.');
        }

        $section->delete();

        return $this->noContent();
    }

    /**
     * Add a reference of a global section to a page.
     */
    public function addToPage(Request $request, SiteSection $section): JsonResponse
    {
        if (! $section->is_global) {
            return $this->notFound('Sección global no encontrada.');
        }

        $data = $request->validate([
            'page_id' => ['required', 'integer', 'exists:site_pages,id'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $page = \App\Models\SitePage::findOrFail($data['page_id']);
        $maxSort = $page->sections()->max('sort_order') ?? -1;

        // Create a copy of the global section in the page
        $copy = $section->replicate(['id', 'site_id']);
        $copy->page_id = $page->id;
        $copy->sort_order = $data['sort_order'] ?? $maxSort + 1;
        $copy->is_global = false;
        $copy->global_name = null;
        $copy->save();

        return $this->created(new SiteSectionResource($copy));
    }
}
