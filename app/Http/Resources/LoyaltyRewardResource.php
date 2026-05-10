<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\LoyaltyReward;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LoyaltyReward
 */
final class LoyaltyRewardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'loyalty_program_id' => $this->loyalty_program_id,
            'reward_type' => $this->reward_type,
            'required_points' => $this->required_points,
            'description' => $this->description,
            'discount' => $this->discount,
            'discount_mode' => $this->discount_mode,
            'discount_applicability' => $this->discount_applicability,
            'discount_max_amount' => $this->discount_max_amount,
            'reward_product_id' => $this->reward_product_id,
            'reward_product_qty' => $this->reward_product_qty,
            'reward_product' => $this->whenLoaded('rewardProduct', fn () => [
                'id' => $this->rewardProduct->id,
                'name' => $this->rewardProduct->full_name ?? $this->rewardProduct->sku,
            ]),
            'discount_product_ids' => $this->discountProducts->pluck('id'),
            'discount_category_ids' => $this->discountCategories->pluck('id'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
