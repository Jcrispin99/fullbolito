<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Partner;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Partner
 */
final class SupplierResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'is_supplier' => (bool) $this->is_supplier,
            'is_customer' => (bool) $this->is_customer,
            'document_type' => $this->document_type,
            'document_number' => $this->document_number,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'ubigeo' => $this->ubigeo,
            'payment_terms' => $this->payment_terms,
            'provider_category' => $this->provider_category,
            'status' => $this->status,
            'notes' => $this->notes,
            'company_id' => $this->company_id,
            'company' => new CompanyResource($this->whenLoaded('company')),

            // Appends
            'display_name' => $this->display_name,

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
