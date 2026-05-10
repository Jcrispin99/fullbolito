<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Tenant\V1;

use Illuminate\Foundation\Http\FormRequest;

final class SiteSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $spacingSizes = 'none,xs,sm,md,lg,xl,2xl';

        $rules = [
            'block_type_key' => ['required', 'string', 'max:100'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'layout' => ['sometimes', 'array'],
            'layout.width' => ['sometimes', 'string', 'in:full,contained,narrow'],
            'layout.padding_top' => ['sometimes', 'string', "in:{$spacingSizes}"],
            'layout.padding_bottom' => ['sometimes', 'string', "in:{$spacingSizes}"],
            'layout.padding_x' => ['sometimes', 'string', 'in:none,sm,md,lg'],
            'layout.padding_y' => ['sometimes', 'string', 'in:none,sm,md,lg,xl'], // legacy
            'layout.margin_top' => ['sometimes', 'string', "in:{$spacingSizes}"],
            'layout.margin_bottom' => ['sometimes', 'string', "in:{$spacingSizes}"],
            'layout.bg_type' => ['sometimes', 'string', 'in:none,color,image,gradient,video'],
            'layout.bg_value' => ['nullable', 'string'],
            'layout.bg_overlay' => ['nullable', 'string', 'max:20'],
            'layout.bg_position' => ['sometimes', 'string', 'in:center,top,bottom,left,right'],
            'layout.bg_size' => ['sometimes', 'string', 'in:cover,contain'],
            'layout.text_align' => ['sometimes', 'string', 'in:left,center,right'],
            'layout.anchor_id' => ['nullable', 'string', 'max:100'],
            'layout.visibility' => ['sometimes', 'string', 'in:all,desktop,tablet,mobile'],
            'parent_id' => ['nullable', 'integer', 'exists:site_sections,id'],
            'column_index' => ['sometimes', 'integer', 'min:0'],
            'position_x' => ['sometimes', 'numeric'],
            'position_y' => ['sometimes', 'numeric'],
            'element_width' => ['nullable', 'numeric', 'min:0'],
            'element_height' => ['nullable', 'numeric', 'min:0'],
            'rotation' => ['sometimes', 'numeric', 'min:-360', 'max:360'],
            'position_mode' => ['sometimes', 'string', 'in:flow,absolute'],
            'content' => ['sometimes', 'array'],
            'style_overrides' => ['nullable', 'array'],
            'is_visible' => ['sometimes', 'boolean'],
        ];

        if ($this->isMethod('patch') || $this->isMethod('put')) {
            $rules['block_type_key'] = ['sometimes', 'string', 'max:100'];
        }

        return $rules;
    }
}
