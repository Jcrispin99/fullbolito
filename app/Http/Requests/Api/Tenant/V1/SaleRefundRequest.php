<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Tenant\V1;

use Illuminate\Foundation\Http\FormRequest;

final class SaleRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_product_id' => ['required', 'integer', 'exists:product_products,id'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.0001'],
        ];
    }

    public function messages(): array
    {
        return [
            'lines.required' => 'Debe especificar al menos una línea para devolver.',
            'lines.*.product_product_id.required' => 'El producto es obligatorio en cada línea.',
            'lines.*.quantity.required' => 'La cantidad es obligatoria en cada línea.',
            'lines.*.quantity.min' => 'La cantidad debe ser mayor a 0.',
        ];
    }
}
