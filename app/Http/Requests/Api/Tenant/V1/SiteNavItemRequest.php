<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Tenant\V1;

use Illuminate\Foundation\Http\FormRequest;

final class SiteNavItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'label' => ['required', 'string', 'max:100'],
            'type' => ['required', 'string', 'in:page,url,anchor'],
            'target' => ['required', 'string', 'max:2048'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'open_new_tab' => ['sometimes', 'boolean'],
            'parent_id' => ['nullable', 'integer', 'exists:site_nav_items,id'],
        ];

        if ($this->isMethod('patch') || $this->isMethod('put')) {
            $rules = array_map(function ($rule) {
                array_unshift($rule, 'sometimes');

                return $rule;
            }, $rules);
        }

        return $rules;
    }
}
