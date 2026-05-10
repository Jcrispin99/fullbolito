<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\Tenant\V1\SiteSectionRequest;
use App\Http\Resources\SiteSectionResource;
use App\Models\SiteBlockCatalog;
use App\Models\SitePage;
use App\Models\SiteSection;
use App\Services\BlockSchemaValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class BuilderSectionController extends ApiController
{
    public function __construct(
        private readonly BlockSchemaValidator $validator,
    ) {}

    public function index(SitePage $page): JsonResponse
    {
        $sections = $page->sections()->ordered()->get();

        return $this->success(SiteSectionResource::collection($sections));
    }

    public function store(SiteSectionRequest $request, SitePage $page): JsonResponse
    {
        $data = $request->validated();

        $catalog = SiteBlockCatalog::where('key', $data['block_type_key'])->first();

        if (! $catalog) {
            return $this->error("Tipo de bloque '{$data['block_type_key']}' no existe.", 422);
        }

        $content = $data['content'] ?? $catalog->default_content;
        $layout = $data['layout'] ?? $catalog->default_layout;

        // Skip required-field validation while building drafts; required fields
        // are enforced when publishing (in the Publish flow), not when adding blocks.
        $validation = $this->validator->validate($data['block_type_key'], $content, $data['style_overrides'] ?? [], skipRequired: true);

        if (! $validation['valid']) {
            return $this->validationError($validation['errors']);
        }

        $maxSort = $page->sections()->max('sort_order') ?? -1;

        $section = $page->sections()->create([
            'block_type_key' => $data['block_type_key'],
            'sort_order' => $data['sort_order'] ?? $maxSort + 1,
            'layout' => $layout,
            'content' => $content,
            'style_overrides' => $data['style_overrides'] ?? null,
            'is_visible' => $data['is_visible'] ?? true,
        ]);

        return $this->created(new SiteSectionResource($section));
    }

    public function show(SitePage $page, SiteSection $section): JsonResponse
    {
        return $this->success(new SiteSectionResource($section));
    }

    public function update(SiteSectionRequest $request, SitePage $page, SiteSection $section): JsonResponse
    {
        $data = $request->validated();

        $content = $data['content'] ?? $section->content;
        $styleOverrides = array_key_exists('style_overrides', $data) ? $data['style_overrides'] : $section->style_overrides;

        $blockTypeKey = $data['block_type_key'] ?? $section->block_type_key;

        // While editing drafts, missing required fields don't block saves —
        // type/format errors still do. Required is enforced when publishing the page.
        $validation = $this->validator->validate($blockTypeKey, $content, $styleOverrides ?? [], skipRequired: true);

        if (! $validation['valid']) {
            return $this->validationError($validation['errors']);
        }

        $section->update($data);

        return $this->success(new SiteSectionResource($section));
    }

    public function destroy(SitePage $page, SiteSection $section): JsonResponse
    {
        $section->delete();

        return $this->noContent();
    }

    public function reorder(Request $request, SitePage $page): JsonResponse
    {
        $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        foreach ($request->input('ids') as $index => $id) {
            $page->sections()->where('id', $id)->update(['sort_order' => $index]);
        }

        $sections = $page->sections()->ordered()->get();

        return $this->success(SiteSectionResource::collection($sections));
    }

    public function duplicate(SitePage $page, SiteSection $section): JsonResponse
    {
        $maxSort = $page->sections()->max('sort_order') ?? 0;

        $newSection = $section->replicate(['id']);
        $newSection->sort_order = $maxSort + 1;
        $newSection->save();

        return $this->created(new SiteSectionResource($newSection));
    }
}
