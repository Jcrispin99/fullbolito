<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Lot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Lot
 */
final class LotResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $totalStock = $this->relationLoaded('lotInventories')
            ? (float) $this->lotInventories->sum('quantity_balance')
            : null;

        $isExpired = $this->expires_at !== null && $this->expires_at->isPast();
        $daysToExpire = $this->expires_at !== null
            ? (int) now()->startOfDay()->diffInDays($this->expires_at->copy()->startOfDay(), false)
            : null;

        return [
            'id' => $this->id,
            'product_product_id' => $this->product_product_id,
            'company_id' => $this->company_id,
            'lot_number' => $this->lot_number,
            'manufactured_at' => $this->manufactured_at?->format('Y-m-d'),
            'expires_at' => $this->expires_at?->format('Y-m-d'),
            'is_expired' => $isExpired,
            'days_to_expire' => $daysToExpire,
            'supplier_id' => $this->supplier_id,
            'purchase_id' => $this->purchase_id,
            'initial_quantity' => (float) $this->initial_quantity,
            'initial_cost' => (float) $this->initial_cost,
            'status' => $this->status,
            'notes' => $this->notes,
            'total_stock' => $totalStock,

            // Relations (only when loaded)
            'product_product' => new ProductProductResource($this->whenLoaded('productProduct')),
            'supplier' => new SupplierResource($this->whenLoaded('supplier')),
            'purchase' => $this->whenLoaded('purchase', fn () => [
                'id' => $this->purchase->id,
                'serie' => $this->purchase->serie,
                'correlative' => $this->purchase->correlative,
                'sequence_code' => trim(
                    ($this->purchase->serie ?? '') .
                    ($this->purchase->correlative ? '-' . $this->purchase->correlative : '')
                ),
            ]),
            'inventories_by_warehouse' => $this->whenLoaded('lotInventories', fn () => $this->lotInventories
                ->map(fn ($li) => [
                    'warehouse_id' => $li->warehouse_id,
                    'warehouse_name' => $li->warehouse?->name,
                    'quantity_balance' => (float) $li->quantity_balance,
                ])->values()),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
