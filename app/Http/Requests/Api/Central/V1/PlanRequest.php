<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Central\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlanRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Solo superadmin puede gestionar planes (se verificará en controller/middleware)
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $planId = $this->route('plan') ? $this->route('plan')->id : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('plans', 'slug')->ignore($planId)],
            'price' => ['required', 'numeric', 'min:0'],
            'duration_days' => ['required', 'integer', 'min:1'],
            'is_active' => ['boolean'],
            'includes_all_modules' => ['boolean'],
            'module_ids' => ['array'],
            'module_ids.*' => ['integer', Rule::exists('modules', 'id')],
        ];
    }
}
