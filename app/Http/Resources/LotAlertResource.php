<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\LotAlert;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LotAlert
 */
final class LotAlertResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lot_id' => $this->lot_id,
            'product_product_id' => $this->product_product_id,
            'company_id' => $this->company_id,
            'alert_type' => $this->alert_type,
            'days_until_expiry' => $this->days_until_expiry,
            'alert_date' => $this->alert_date?->format('Y-m-d'),
            'message' => $this->message,
            'status' => $this->status,
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),

            'lot' => $this->whenLoaded('lot', fn () => [
                'id' => $this->lot->id,
                'lot_number' => $this->lot->lot_number,
                'expires_at' => $this->lot->expires_at?->format('Y-m-d'),
                'status' => $this->lot->status,
            ]),
            'product_product' => new ProductProductResource($this->whenLoaded('productProduct')),
        ];
    }
}
