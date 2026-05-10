<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Company
 */
final class CompanyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->trade_name ?? $this->business_name,
            'business_name' => $this->business_name,
            'trade_name' => $this->trade_name,
            'ruc' => $this->ruc,
            'address' => $this->address,
            'phone' => $this->phone,
            'email' => $this->email,
            'ubigeo' => $this->ubigeo,
            'is_active' => (bool) $this->is_active,
            'parent_id' => $this->parent_id,
            'branch_code' => $this->branch_code,
            'is_main' => (bool) $this->is_main,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
