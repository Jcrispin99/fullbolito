<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class SitePageVersionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'page_id' => $this->page_id,
            'version_number' => $this->version_number,
            'title' => $this->title,
            'slug' => $this->slug,
            'sections_snapshot' => $this->sections_snapshot,
            'seo_snapshot' => $this->seo_snapshot,
            'reason' => $this->reason,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
