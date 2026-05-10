<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\SiteResource;
use App\Http\Resources\SiteTemplateResource;
use App\Models\Site;
use App\Models\SitePage;
use App\Models\SiteNav;
use App\Models\SiteNavItem;
use App\Models\SiteSection;
use App\Models\SiteTemplate;
use App\Models\SiteTheme;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class BuilderTemplateController extends ApiController
{
    /**
     * List available templates.
     */
    public function index(Request $request): JsonResponse
    {
        $query = SiteTemplate::active()->ordered();

        if ($request->has('category')) {
            $query->byCategory($request->input('category'));
        }

        return $this->success(SiteTemplateResource::collection($query->get()));
    }

    /**
     * Show a specific template (without full snapshot).
     */
    public function show(SiteTemplate $template): JsonResponse
    {
        return $this->success(new SiteTemplateResource($template));
    }

    /**
     * Save the current site as a template.
     */
    public function saveAsTemplate(Request $request): JsonResponse
    {
        $site = Site::query()->first();

        if (! $site) {
            return $this->notFound('No se encontró el sitio.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:100', 'unique:site_templates,slug'],
            'description' => ['nullable', 'string', 'max:500'],
            'category' => ['nullable', 'string', 'max:50'],
        ]);

        $site->load(['activeTheme', 'pages.sections', 'navs.rootItems.children']);

        $snapshot = [
            'site' => [
                'name' => $site->name,
                'settings' => $site->settings,
            ],
            'theme' => $site->activeTheme ? [
                'name' => $site->activeTheme->name,
                'colors' => $site->activeTheme->colors,
                'typography' => $site->activeTheme->typography,
                'spacing' => $site->activeTheme->spacing,
                'borders' => $site->activeTheme->borders,
            ] : null,
            'pages' => $site->pages->map(fn (SitePage $page) => [
                'title' => $page->title,
                'slug' => $page->slug,
                'type' => $page->type,
                'is_homepage' => $page->is_homepage,
                'seo_title' => $page->seo_title,
                'seo_description' => $page->seo_description,
                'sections' => $page->sections->map(fn (SiteSection $s) => [
                    'block_type_key' => $s->block_type_key,
                    'sort_order' => $s->sort_order,
                    'layout' => $s->layout,
                    'content' => $s->content,
                    'style_overrides' => $s->style_overrides,
                    'is_visible' => $s->is_visible,
                ])->toArray(),
            ])->toArray(),
            'navs' => $site->navs->map(fn (SiteNav $nav) => [
                'location' => $nav->location,
                'config' => $nav->config,
                'items' => $nav->rootItems->map(fn (SiteNavItem $item) => $this->serializeNavItem($item))->toArray(),
            ])->toArray(),
        ];

        $template = SiteTemplate::create([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? null,
            'category' => $data['category'] ?? 'general',
            'site_snapshot' => $snapshot,
        ]);

        return $this->created(new SiteTemplateResource($template));
    }

    /**
     * Apply a template to the current site (replaces all content).
     */
    public function applyTemplate(SiteTemplate $template): JsonResponse
    {
        $site = Site::query()->first();

        if (! $site) {
            return $this->notFound('No se encontró el sitio.');
        }

        $snapshot = $template->site_snapshot;

        // Update site settings
        if (isset($snapshot['site'])) {
            $site->update([
                'settings' => $snapshot['site']['settings'] ?? $site->settings,
            ]);
        }

        // Apply theme
        if (isset($snapshot['theme'])) {
            $theme = $site->activeTheme;
            if ($theme) {
                $theme->update($snapshot['theme']);
            } else {
                SiteTheme::create(array_merge($snapshot['theme'], [
                    'site_id' => $site->id,
                    'is_active' => true,
                ]));
            }
        }

        // Replace pages
        $site->pages()->delete();

        if (isset($snapshot['pages'])) {
            foreach ($snapshot['pages'] as $pageData) {
                $sections = $pageData['sections'] ?? [];
                unset($pageData['sections']);

                $pageData['site_id'] = $site->id;
                $pageData['status'] = 'draft';
                $page = SitePage::create($pageData);

                foreach ($sections as $sectionData) {
                    $page->sections()->create($sectionData);
                }
            }
        }

        // Replace navs
        $site->navs()->delete();

        if (isset($snapshot['navs'])) {
            foreach ($snapshot['navs'] as $navData) {
                $items = $navData['items'] ?? [];
                unset($navData['items']);

                $navData['site_id'] = $site->id;
                $nav = SiteNav::create($navData);

                foreach ($items as $itemData) {
                    $this->createNavItemFromSnapshot($nav, $itemData, null);
                }
            }
        }

        $site->load(['activeTheme', 'pages.sections', 'navs.rootItems.children']);

        return $this->success(new SiteResource($site));
    }

    /**
     * Duplicate the current site (creates a new site within the tenant).
     */
    public function duplicateSite(): JsonResponse
    {
        $site = Site::query()->first();

        if (! $site) {
            return $this->notFound('No se encontró el sitio.');
        }

        $site->load(['activeTheme', 'pages.sections', 'navs.rootItems.children']);

        $newSite = $site->replicate(['id']);
        $newSite->name = $site->name . ' (copia)';
        $newSite->status = 'draft';
        $newSite->save();

        // Duplicate theme
        if ($site->activeTheme) {
            $newTheme = $site->activeTheme->replicate(['id']);
            $newTheme->site_id = $newSite->id;
            $newTheme->save();
        }

        // Duplicate pages with sections
        foreach ($site->pages as $page) {
            $newPage = $page->replicate(['id', 'published_at']);
            $newPage->site_id = $newSite->id;
            $newPage->status = 'draft';
            $newPage->save();

            foreach ($page->sections as $section) {
                $newSection = $section->replicate(['id']);
                $newSection->page_id = $newPage->id;
                $newSection->save();
            }
        }

        // Duplicate navs with items
        foreach ($site->navs as $nav) {
            $newNav = $nav->replicate(['id']);
            $newNav->site_id = $newSite->id;
            $newNav->save();

            foreach ($nav->rootItems as $item) {
                $this->duplicateNavItem($item, $newNav->id, null);
            }
        }

        $newSite->load(['activeTheme', 'pages.sections', 'navs.rootItems.children']);

        return $this->created(new SiteResource($newSite));
    }

    private function serializeNavItem(SiteNavItem $item): array
    {
        return [
            'label' => $item->label,
            'type' => $item->type,
            'target' => $item->target,
            'sort_order' => $item->sort_order,
            'open_new_tab' => $item->open_new_tab,
            'children' => $item->children->map(fn (SiteNavItem $child) => $this->serializeNavItem($child))->toArray(),
        ];
    }

    private function createNavItemFromSnapshot(SiteNav $nav, array $itemData, ?int $parentId): void
    {
        $children = $itemData['children'] ?? [];
        unset($itemData['children']);

        $itemData['nav_id'] = $nav->id;
        $itemData['parent_id'] = $parentId;

        $item = SiteNavItem::create($itemData);

        foreach ($children as $childData) {
            $this->createNavItemFromSnapshot($nav, $childData, $item->id);
        }
    }

    private function duplicateNavItem(SiteNavItem $item, int $navId, ?int $parentId): void
    {
        $newItem = $item->replicate(['id']);
        $newItem->nav_id = $navId;
        $newItem->parent_id = $parentId;
        $newItem->save();

        foreach ($item->children as $child) {
            $this->duplicateNavItem($child, $navId, $newItem->id);
        }
    }
}
