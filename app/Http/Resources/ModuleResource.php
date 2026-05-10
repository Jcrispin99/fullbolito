<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Module;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Module
 */
class ModuleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'label' => $this->label,
            'description' => $this->description,
            'icon' => $this->icon,
            'addon_price' => (float) $this->addon_price,
            'stripe_price_id' => $this->stripe_price_id,
            'stripe_product_id' => $this->stripe_product_id,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'is_addon' => $this->isAddon(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
