<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class SiteSectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'column_index' => $this->column_index,
            'block_type_key' => $this->block_type_key,
            'sort_order' => $this->sort_order,
            'position_x' => (float) $this->position_x,
            'position_y' => (float) $this->position_y,
            'element_width' => $this->element_width ? (float) $this->element_width : null,
            'element_height' => $this->element_height ? (float) $this->element_height : null,
            'rotation' => (float) $this->rotation,
            'position_mode' => $this->position_mode ?? 'flow',
            'layout' => $this->layout,
            'content' => $this->content,
            'style_overrides' => $this->style_overrides,
            'is_visible' => (bool) $this->is_visible,
            'is_global' => (bool) $this->is_global,
            'global_name' => $this->global_name,
            'children' => self::collection($this->whenLoaded('children')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
