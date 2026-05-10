<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Movement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Movement
 */
final class MovementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'serie' => $this->serie,
            'correlative' => $this->correlative,
            'sequence_code' => "{$this->serie}-{$this->correlative}",
            'date' => $this->date->toIso8601String(),
            'status' => $this->status,
            'total' => (float) $this->total,
            'observation' => $this->observation,
            'reason' => $this->reason,
            'rejection_reason' => $this->rejection_reason,

            'warehouse_id' => $this->warehouse_id,
            'company_id' => $this->company_id,
            'journal_id' => $this->journal_id,
            'transfer_id' => $this->transfer_id,

            // Workflow timestamps
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'posted_at' => $this->posted_at?->toIso8601String(),
            'rejected_at' => $this->rejected_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),

            // Workflow users (ids)
            'created_user_id' => $this->created_user_id,
            'submitted_user_id' => $this->submitted_user_id,
            'posted_user_id' => $this->posted_user_id,
            'rejected_user_id' => $this->rejected_user_id,
            'cancelled_user_id' => $this->cancelled_user_id,

            // Relaciones
            'warehouse' => new WarehouseResource($this->whenLoaded('warehouse')),
            'company' => new CompanyResource($this->whenLoaded('company')),
            'created_user' => new UserResource($this->whenLoaded('createdUser')),
            'submitted_user' => new UserResource($this->whenLoaded('submittedUser')),
            'posted_user' => new UserResource($this->whenLoaded('postedUser')),
            'rejected_user' => new UserResource($this->whenLoaded('rejectedUser')),
            'cancelled_user' => new UserResource($this->whenLoaded('cancelledUser')),
            'lines' => ProductableResource::collection($this->whenLoaded('productables')),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
