<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class SiteBlockCatalogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'name' => $this->name,
            'description' => $this->description,
            'category' => $this->category,
            'icon' => $this->icon,
            'feature_gate' => $this->feature_gate,
            'schema' => $this->schema,
            'default_content' => $this->default_content,
            'default_layout' => $this->default_layout,
            'sort_order' => $this->sort_order,
            'is_active' => (bool) $this->is_active,
        ];
    }
}
