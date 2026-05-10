<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class SiteNavItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'type' => $this->type,
            'target' => $this->target,
            'sort_order' => $this->sort_order,
            'open_new_tab' => (bool) $this->open_new_tab,
            'children' => self::collection($this->whenLoaded('children')),
        ];
    }
}
