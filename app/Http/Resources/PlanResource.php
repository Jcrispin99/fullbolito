<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Plan
 */
class PlanResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'price' => (float) $this->price,
            'duration_days' => $this->duration_days,
            'is_active' => $this->is_active,
            'includes_all_modules' => (bool) $this->includes_all_modules,
            'module_ids' => $this->whenLoaded(
                'modules',
                fn () => $this->modules->pluck('id')->all(),
                [],
            ),
            'modules' => $this->whenLoaded(
                'modules',
                fn () => $this->modules
                    ->map(fn ($m) => [
                        'id' => $m->id,
                        'key' => $m->key,
                        'label' => $m->label,
                        'icon' => $m->icon,
                    ])
                    ->values()
                    ->all(),
                [],
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
