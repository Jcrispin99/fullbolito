<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Tenant\V1;

use Illuminate\Foundation\Http\FormRequest;

final class TransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'from_warehouse_id' => ['required', 'exists:warehouses,id'],
            'to_warehouse_id' => ['required', 'exists:warehouses,id', 'different:from_warehouse_id'],
            'company_id' => ['required', 'exists:companies,id'],
            'observation' => ['nullable', 'string'],
            'products' => ['required', 'array', 'min:1'],
            'products.*.product_product_id' => ['required', 'exists:product_products,id'],
            'products.*.quantity' => ['required', 'numeric', 'min:0.0001'],
            // Obligatorio cuando el producto es tracked_by_lot; se valida en controlador.
            'products.*.lot_id' => ['nullable', 'exists:lots,id'],
        ];

        if ($this->isMethod('patch')) {
            $rules = array_map(function (array $rule) {
                array_unshift($rule, 'sometimes');

                return $rule;
            }, $rules);
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'to_warehouse_id.different' => 'El almacén de destino debe ser distinto al de origen.',
            'products.required' => 'Debe incluir al menos un producto en la transferencia.',
            'products.*.product_product_id.required' => 'El ID del producto variante es obligatorio.',
            'products.*.quantity.min' => 'La cantidad debe ser mayor a cero.',
        ];
    }
}
