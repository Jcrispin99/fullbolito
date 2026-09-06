<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Central\V1;

use App\Models\Module;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ModuleRequest extends FormRequest
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
        $routeModule = $this->route('module');
        $moduleId = $routeModule instanceof Module ? $routeModule->id : null;

        return [
            'key' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_\-]+$/', Rule::unique('modules', 'key')->ignore($moduleId)],
            'label' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'icon' => ['nullable', 'string', 'max:64'],
            'addon_price' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'key.regex' => 'La clave solo puede contener letras minúsculas, números, guiones y guiones bajos.',
        ];
    }
}
