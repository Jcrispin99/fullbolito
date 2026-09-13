<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Central\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class PlanRequest extends FormRequest
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
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'min:0'],
            'duration_days' => ['required', 'integer', 'min:1'],
            'billing_rank' => ['sometimes', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['boolean'],
            'includes_all_modules' => ['boolean'],
            'sunat_worker_slots' => ['sometimes', 'integer', Rule::in([1, 2, 4, 8])],
            'sunat_dedicated_queue' => ['boolean'],
            'module_ids' => ['array'],
            'module_ids.*' => ['integer', Rule::exists('modules', 'id')],
        ];
    }
}
