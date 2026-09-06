<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Tenant\V1;

use Illuminate\Foundation\Http\FormRequest;

final class CourtRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'code' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],

            'sport' => ['required', 'string', 'max:50'],
            'surface' => ['nullable', 'string', 'max:50'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'slot_duration_minutes' => ['nullable', 'integer', 'min:5', 'max:1440'],

            // Sede (la company a la que pertenece la cancha)
            'company_id' => ['required', 'integer', 'exists:companies,id'],

            // Precio que se asigna al producto facturable oculto
            'price' => ['required', 'numeric', 'min:0'],

            'is_active' => ['nullable', 'boolean'],
        ];

        if ($this->isMethod('patch')) {
            $rules = array_map(function (array $rule): array {
                array_unshift($rule, 'sometimes');

                return $rule;
            }, $rules);
        }

        return $rules;
    }
}
