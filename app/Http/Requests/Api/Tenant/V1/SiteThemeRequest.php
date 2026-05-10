<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Tenant\V1;

use Illuminate\Foundation\Http\FormRequest;

final class SiteThemeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'colors' => ['sometimes', 'array'],
            'colors.primary' => ['sometimes', 'string', 'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'colors.secondary' => ['sometimes', 'string', 'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'colors.accent' => ['sometimes', 'string', 'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'colors.background' => ['sometimes', 'string', 'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'colors.surface' => ['sometimes', 'string', 'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'colors.text' => ['sometimes', 'string', 'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'colors.text_muted' => ['sometimes', 'string', 'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'typography' => ['sometimes', 'array'],
            'typography.heading_font' => ['sometimes', 'string', 'max:100'],
            'typography.body_font' => ['sometimes', 'string', 'max:100'],
            'typography.base_size' => ['sometimes', 'integer', 'min:12', 'max:24'],
            'typography.scale' => ['sometimes', 'numeric', 'min:1.0', 'max:2.0'],
            'typography.line_height' => ['sometimes', 'numeric', 'min:1.0', 'max:3.0'],
            'spacing' => ['sometimes', 'array'],
            'spacing.section_padding' => ['sometimes', 'integer', 'min:0', 'max:200'],
            'spacing.container_max_width' => ['sometimes', 'integer', 'min:800', 'max:1920'],
            'spacing.block_gap' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'borders' => ['sometimes', 'array'],
            'borders.radius' => ['sometimes', 'string', 'in:none,sm,md,lg,full'],
            'borders.button_radius' => ['sometimes', 'string', 'in:none,sm,md,lg,full'],
        ];
    }
}
