<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Tenant\V1;

use Illuminate\Foundation\Http\FormRequest;

final class PurchaseRequest extends FormRequest
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
            'partner_id' => ['required', 'exists:partners,id'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'company_id' => ['required', 'exists:companies,id'],
            'buyer_id' => ['nullable', 'exists:users,id'],
            'vendor_bill_number' => ['nullable', 'string', 'max:255'],
            'vendor_bill_date' => ['nullable', 'date'],
            'observation' => ['nullable', 'string'],
            'products' => ['required', 'array', 'min:1'],
            'products.*.product_product_id' => ['required', 'exists:product_products,id'],
            'products.*.quantity' => ['nullable', 'numeric', 'min:0.01'],
            'products.*.price' => ['nullable', 'numeric', 'min:0'],
            'products.*.tax_id' => ['nullable', 'exists:taxes,id'],
            'products.*.uom_id' => ['nullable', 'exists:unit_of_measures,id'],
            'products.*.quantity_uom' => ['nullable', 'numeric', 'min:0.01'],
            'products.*.price_uom' => ['nullable', 'numeric', 'min:0'],
            // Campos de lote (opcionales; se validan con lógica de negocio en el post)
            'products.*.lot_number' => ['nullable', 'string', 'max:50'],
            'products.*.manufactured_at' => ['nullable', 'date'],
            'products.*.expires_at' => ['nullable', 'date', 'after_or_equal:products.*.manufactured_at'],
        ];

        if ($this->isMethod('patch')) {
            $rules = array_map(function ($rule) {
                if (is_array($rule)) {
                    array_unshift($rule, 'sometimes');

                    return $rule;
                }

                return 'sometimes|'.$rule;
            }, $rules);
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'products.required' => 'Debe incluir al menos un producto en la compra.',
            'products.*.product_product_id.required' => 'El ID del producto variante es obligatorio.',
        ];
    }
}
