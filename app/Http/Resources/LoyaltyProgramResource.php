<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\LoyaltyProgram;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LoyaltyProgram
 */
final class LoyaltyProgramResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'program_type' => $this->program_type,
            'is_pos' => (bool) $this->is_pos,
            'is_web' => (bool) $this->is_web,
            'is_sales' => (bool) $this->is_sales,
            'applies_on' => $this->applies_on,
            'trigger' => $this->trigger,
            'point_name' => $this->point_name,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'max_uses' => $this->max_uses,
            'max_uses_per_customer' => $this->max_uses_per_customer,
            'current_uses' => $this->current_uses,
            'is_active' => (bool) $this->is_active,
            'is_available' => $this->isAvailable(),
            'rules' => LoyaltyRuleResource::collection($this->whenLoaded('rules')),
            'rewards' => LoyaltyRewardResource::collection($this->whenLoaded('rewards')),
            'cards_count' => $this->whenCounted('cards'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
