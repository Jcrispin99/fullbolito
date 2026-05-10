<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\LoyaltyRule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LoyaltyRule
 */
final class LoyaltyRuleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'loyalty_program_id' => $this->loyalty_program_id,
            'reward_point_amount' => $this->reward_point_amount,
            'reward_point_mode' => $this->reward_point_mode,
            'minimum_qty' => $this->minimum_qty,
            'minimum_amount' => $this->minimum_amount,
            'code' => $this->code,
            'conditions' => $this->conditions,
            'product_variant_ids' => $this->productVariants->pluck('id'),
            'product_template_ids' => $this->productTemplates->pluck('id'),
            'category_ids' => $this->categories->pluck('id'),
            'product_variants' => $this->whenLoaded('productVariants', fn () => $this->productVariants->map(fn ($p) => ['id' => $p->id, 'name' => $p->full_name ?? $p->sku])),
            'product_templates' => $this->whenLoaded('productTemplates', fn () => $this->productTemplates->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])),
            'categories' => $this->whenLoaded('categories', fn () => $this->categories->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
