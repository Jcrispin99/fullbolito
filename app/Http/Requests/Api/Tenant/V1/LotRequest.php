<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Tenant\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class LotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Mutable fields on a Lot. Fields like product_product_id, company_id,
     * purchase_id, supplier_id, initial_quantity and initial_cost are
     * intentionally excluded — they are set at creation by the purchase
     * post flow and changing them would corrupt the kardex.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $lotId = $this->route('lot')?->id;

        return [
            'lot_number' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('lots', 'lot_number')
                    ->where(fn ($q) => $q->where('product_product_id', $this->route('lot')?->product_product_id))
                    ->ignore($lotId)
                    ->whereNull('deleted_at'),
            ],
            'manufactured_at' => ['sometimes', 'nullable', 'date'],
            'expires_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:manufactured_at'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', 'required', Rule::in(['active', 'blocked', 'expired', 'depleted'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'lot_number.unique' => 'Ya existe un lote con ese número para este producto.',
            'expires_at.after_or_equal' => 'La fecha de vencimiento debe ser posterior o igual a la de fabricación.',
            'status.in' => 'Estado de lote no válido.',
        ];
    }
}
