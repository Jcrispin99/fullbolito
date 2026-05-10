<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Tenant\V1;

use Illuminate\Foundation\Http\FormRequest;

final class PosCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'pos_config_id' => ['required', 'integer', 'exists:pos_configs,id'],
            'pos_session_id' => ['required', 'integer', 'exists:pos_sessions,id'],
            'partner_id' => ['nullable', 'integer', 'exists:partners,id'],
            'journal_id' => ['required', 'integer', 'exists:journals,id'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'notes' => ['nullable', 'string'],
            'loyalty_card_id' => ['nullable', 'integer', 'exists:loyalty_cards,id'],
            'redeem_points' => ['nullable', 'integer', 'min:1'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_product_id' => ['required', 'integer', 'exists:product_products,id'],
            'items.*.tax_id' => ['nullable', 'integer', 'exists:taxes,id'],
            'items.*.uom_id' => ['nullable', 'integer', 'exists:unit_of_measures,id'],
            'items.*.lot_id' => ['nullable', 'integer', 'exists:lots,id'],
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.payment_method_id' => ['required', 'integer', 'exists:payment_methods,id'],
            // Permitimos 0 para casos donde el canje de puntos cubre el total
            // y no hay monto en efectivo/tarjeta que cobrar.
            'payments.*.amount' => ['required', 'numeric', 'min:0'],
            'payments.*.reference' => ['nullable', 'string', 'max:255'],
        ];

        if ($this->has('items') && is_array($this->input('items'))) {
            foreach ($this->input('items') as $index => $item) {
                if (isset($item['uom_id'])) {
                    $rules["items.{$index}.quantity_uom"] = ['required', 'numeric', 'min:0.01'];
                    $rules["items.{$index}.price_uom"] = ['required', 'numeric', 'min:0'];
                } else {
                    $rules["items.{$index}.quantity"] = ['required', 'numeric', 'min:0.01'];
                    $rules["items.{$index}.price"] = ['required', 'numeric', 'min:0'];
                }
            }
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'items.required' => 'La venta POS debe contener al menos un producto.',
            'items.*.product_product_id.required' => 'El producto es obligatorio en cada línea.',
            'items.*.quantity.required' => 'La cantidad es obligatoria cuando no se usa unidad alternativa.',
            'items.*.price.required' => 'El precio es obligatorio cuando no se usa unidad alternativa.',
            'items.*.quantity_uom.required' => 'La cantidad en unidad alternativa es obligatoria.',
            'items.*.price_uom.required' => 'El precio en unidad alternativa es obligatorio.',
            'payments.required' => 'Debe registrar al menos un pago para continuar.',
        ];
    }
}
