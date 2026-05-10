<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Tenant\V1;

use Illuminate\Foundation\Http\FormRequest;

final class SiteNavRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'config' => ['required', 'array'],
            'config.bg_color' => ['sometimes', 'string', 'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'config.text_color' => ['sometimes', 'string', 'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'config.font' => ['sometimes', 'string', 'in:system,sans,serif,mono'],
            'config.sticky' => ['sometimes', 'boolean'],
            'config.logo' => ['sometimes', 'array'],
            'config.logo.type' => ['sometimes', 'string', 'in:text,image'],
            'config.logo.text' => ['nullable', 'string', 'max:50'],
            'config.logo.image_url' => ['nullable', 'string', 'max:2048'],
            'config.logo.href' => ['sometimes', 'string', 'max:255'],
            'config.logo.position' => ['sometimes', 'string', 'in:left,center,right'],
            'config.menu' => ['sometimes', 'array'],
            'config.menu.position' => ['sometimes', 'string', 'in:left,center,right'],
            'config.menu.style' => ['sometimes', 'string', 'in:default,split'],
            'config.menu.include_home' => ['sometimes', 'boolean'],
        ];
    }
}
