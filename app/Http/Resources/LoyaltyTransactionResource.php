<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\LoyaltyTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LoyaltyTransaction
 */
final class LoyaltyTransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'loyalty_program_id' => $this->loyalty_program_id,
            'loyalty_card_id' => $this->loyalty_card_id,
            'partner_id' => $this->partner_id,
            'type' => $this->type,
            'points' => $this->points,
            'balance' => $this->balance,
            'description' => $this->description,
            'source_type' => $this->source_type ? class_basename($this->source_type) : null,
            'source_id' => $this->source_id,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'partner' => new CustomerResource($this->whenLoaded('partner')),
            'card' => new LoyaltyCardResource($this->whenLoaded('card')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
