<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Tenant\V1;

use Illuminate\Foundation\Http\FormRequest;

final class SiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'settings' => ['sometimes', 'nullable', 'array'],
            'settings.google_analytics' => ['nullable', 'string', 'max:50'],
            'settings.custom_css' => ['nullable', 'string'],
            'settings.custom_head' => ['nullable', 'string'],
        ];
    }
}
