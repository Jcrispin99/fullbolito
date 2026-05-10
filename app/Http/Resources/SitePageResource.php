<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class SitePageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'type' => $this->type,
            'status' => $this->status,
            'is_homepage' => (bool) $this->is_homepage,
            'seo_title' => $this->seo_title,
            'seo_description' => $this->seo_description,
            'og_image_asset_id' => $this->og_image_asset_id,
            'published_at' => $this->published_at?->toIso8601String(),
            'sections' => SiteSectionResource::collection($this->whenLoaded('sections')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
