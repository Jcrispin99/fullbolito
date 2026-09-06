<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Reservation
 */
final class ReservationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'journal_id' => $this->journal_id,
            'serie' => $this->serie,
            'correlative' => $this->correlative,

            'court_id' => $this->court_id,
            'court' => $this->whenLoaded('court', fn () => [
                'id' => $this->court->id,
                'name' => $this->court->name,
                'sport' => $this->court->sport,
                'slug' => $this->court->slug,
            ]),

            'company_id' => $this->company_id,

            'start_at' => $this->start_at?->toIso8601String(),
            'end_at' => $this->end_at?->toIso8601String(),

            'status' => $this->status,
            'is_blocking' => $this->isBlocking(),
            'held_until' => $this->held_until?->toIso8601String(),

            'partner_id' => $this->partner_id,
            'partner' => $this->whenLoaded('partner', fn () => $this->partner ? [
                'id' => $this->partner->id,
                'name' => $this->partner->business_name ?? $this->partner->name ?? null,
            ] : null),

            'customer_name' => $this->customer_name,
            'customer_phone' => $this->customer_phone,
            'customer_email' => $this->customer_email,

            'total' => $this->total,
            'notes' => $this->notes,

            'sale_id' => $this->sale_id,
            'sale' => $this->whenLoaded('sale', fn () => $this->sale ? [
                'id' => $this->sale->id,
                'serie' => $this->sale->serie,
                'correlative' => $this->sale->correlative,
                'document_number' => $this->sale->document_number,
                'subtotal' => $this->sale->subtotal,
                'tax_amount' => $this->sale->tax_amount,
                'total' => $this->sale->total,
                'status' => $this->sale->status,
                'payment_status' => $this->sale->payment_status,
            ] : null),

            'created_by_user_id' => $this->created_by_user_id,
            'created_by' => $this->whenLoaded('createdByUser', fn () => $this->createdByUser ? [
                'id' => $this->createdByUser->id,
                'name' => $this->createdByUser->name,
            ] : null),

            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancellation_reason' => $this->cancellation_reason,

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
