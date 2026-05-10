<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Tenant\V1;

use App\Rules\UbigeoExists;
use Illuminate\Foundation\Http\FormRequest;

final class WarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authentication handled by middleware
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'company_id' => ['nullable', 'exists:companies,id'],
            // Campos SUNAT/GRE: requeridos para emitir guía electrónica,
            // pero opcionales en el form para no romper warehouses existentes.
            'ubigeo' => ['nullable', 'string', 'size:6', new UbigeoExists()],
            'address_line' => ['nullable', 'string', 'max:255'],
            'establishment_code' => ['nullable', 'string', 'size:4'],
        ];

        // Conditional validation for PATCH requests
        if ($this->isMethod('patch')) {
            $rules = array_map(function (array $rule): array {
                array_unshift($rule, 'sometimes');

                return $rule;
            }, $rules);
        }

        return $rules;
    }
}
