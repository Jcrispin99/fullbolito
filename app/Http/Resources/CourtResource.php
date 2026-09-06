<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Court;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Court
 */
final class CourtResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'code' => $this->code,
            'description' => $this->description,
            'sport' => $this->sport,
            'surface' => $this->surface,
            'capacity' => $this->capacity,
            'slot_duration_minutes' => $this->slot_duration_minutes,

            // El precio vive en el ProductProduct asociado, no en courts.
            'price' => $this->whenLoaded(
                'productProduct',
                fn () => $this->productProduct?->price,
            ),

            'company_id' => $this->company_id,
            'company' => new CompanyResource($this->whenLoaded('company')),

            // Identificadores internos del producto facturable (oculto al usuario,
            // pero útil al front si necesita trazabilidad).
            'product_product_id' => $this->product_product_id,

            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
