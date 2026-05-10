<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Tenant
 */
final class TenantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $attributes = $this->resource->getAttributes();
        $data = collect($attributes)
            ->except(['id', 'user_id', 'created_at', 'updated_at', 'data'])
            ->reject(fn($value, $key) => is_string($key) && str_starts_with($key, Tenant::internalPrefix()))
            ->all();

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'domains' => DomainResource::collection($this->whenLoaded('domains')),
            'data' => $data,
            'subscription' => new SubscriptionResource($this->whenLoaded('subscription')),
            'subscriptions' => SubscriptionResource::collection($this->whenLoaded('subscriptions')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
