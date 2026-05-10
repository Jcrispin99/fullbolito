<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Productable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Productable
 */
final class ProductableResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_product_id' => $this->product_product_id,
            'uom_id' => $this->uom_id,
            'quantity' => (float) $this->quantity,
            'price' => (float) $this->price,
            'quantity_uom' => $this->quantity_uom ? (float) $this->quantity_uom : null,
            'price_uom' => $this->price_uom ? (float) $this->price_uom : null,
            'uom_factor' => (float) $this->uom_factor,
            'subtotal' => (float) $this->subtotal,
            'tax_id' => $this->tax_id,
            'tax_rate' => (float) $this->tax_rate,
            'tax_amount' => (float) $this->tax_amount,
            'total' => (float) $this->total,

            // Lot fields
            'lot_id' => $this->lot_id,
            'lot_number' => $this->lot?->lot_number,
            'lot_number_input' => $this->lot_number_input,
            'lot_manufactured_at' => $this->lot_manufactured_at?->format('Y-m-d'),
            'lot_expires_at' => $this->lot_expires_at?->format('Y-m-d'),

            // Relations
            'product' => $this->whenLoaded('productProduct', fn () => [
                'id' => $this->productProduct->id,
                'product_template_id' => $this->productProduct->product_template_id,
                'sku' => $this->productProduct->sku,
                'name' => $this->productProduct->template?->name,
                'attributes' => $this->productProduct->attributeValues->pluck('value')->join(', '),
                'is_tracked_by_lot' => (bool) ($this->productProduct->template?->tracked_by_lot ?? false),
            ]),
            'uom' => $this->whenLoaded('uom', fn () => [
                'id' => $this->uom->id,
                'name' => $this->uom->name,
                'symbol' => $this->uom->symbol,
            ]),
            'tax' => $this->whenLoaded('tax', fn () => [
                'id' => $this->tax->id,
                'name' => $this->tax->name,
                'rate_percent' => $this->tax->rate_percent,
            ]),
            'lot' => $this->whenLoaded('lot', fn () => $this->lot ? [
                'id' => $this->lot->id,
                'lot_number' => $this->lot->lot_number,
                'manufactured_at' => $this->lot->manufactured_at?->format('Y-m-d'),
                'expires_at' => $this->lot->expires_at?->format('Y-m-d'),
            ] : null),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
