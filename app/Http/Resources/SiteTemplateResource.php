<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class SiteTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'thumbnail_url' => $this->thumbnail_url,
            'category' => $this->category,
            'is_active' => (bool) $this->is_active,
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
