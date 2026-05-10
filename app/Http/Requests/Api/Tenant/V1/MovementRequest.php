<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Tenant\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class MovementRequest extends FormRequest
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
            'type' => ['required', Rule::in(['entry', 'exit'])],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'company_id' => ['required', 'exists:companies,id'],
            'reason' => ['nullable', 'string', 'max:255'],
            'observation' => ['nullable', 'string'],
            'date' => ['nullable', 'date'],

            'products' => ['required', 'array', 'min:1'],
            'products.*.product_product_id' => ['required', 'exists:product_products,id'],
            'products.*.quantity' => ['required', 'numeric', 'min:0.0001'],
            'products.*.price' => ['nullable', 'numeric', 'min:0'],
            // Para `exit` con producto trazado: lote obligatorio (validación
            // adicional en MovementService::assertLinesValid).
            // Para `entry` con producto trazado: lote ya creado o se crea desde
            // la capa controller antes del post.
            'products.*.lot_id' => ['nullable', 'exists:lots,id'],
        ];

        if ($this->isMethod('patch') || $this->isMethod('put')) {
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
            'type.in' => 'El tipo de movimiento debe ser "entry" o "exit".',
            'products.required' => 'Debe incluir al menos una línea en el movimiento.',
            'products.*.quantity.min' => 'La cantidad debe ser mayor a cero.',
        ];
    }
}
