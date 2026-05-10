<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Tenant\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CategoryRequest extends FormRequest
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
            'description' => ['nullable', 'string'],
            'parent_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'is_active' => ['boolean'],
        ];

        if ($this->isMethod('patch')) {
            $rules = array_map(function ($rule) {
                array_unshift($rule, 'sometimes');

                return $rule;
            }, $rules);
        }

        return $rules;
    }
}
