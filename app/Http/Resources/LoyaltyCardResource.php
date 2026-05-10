<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\LoyaltyCard;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LoyaltyCard
 */
final class LoyaltyCardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'loyalty_program_id' => $this->loyalty_program_id,
            'partner_id' => $this->partner_id,
            'code' => $this->code,
            'points' => $this->points,
            'expiration_date' => $this->expiration_date?->toDateString(),
            'is_active' => (bool) $this->is_active,
            'is_expired' => $this->isExpired(),
            'is_usable' => $this->isUsable(),
            'program' => new LoyaltyProgramResource($this->whenLoaded('program')),
            'partner' => new CustomerResource($this->whenLoaded('partner')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
