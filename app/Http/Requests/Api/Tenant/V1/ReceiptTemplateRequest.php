<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Tenant\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReceiptTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $blockTypes = [
            'logo', 'company_info', 'doc_title', 'doc_number', 'customer',
            'items_table', 'totals', 'payments', 'loyalty', 'qr',
            'free_text', 'paragraph', 'key_value_list', 'divider', 'spacer',
        ];

        return [
            'layout' => 'required|array',
            'layout.version' => 'required|integer|in:3',
            'layout.paper_width' => ['required', 'string', Rule::in(['thermal_58', 'thermal_80', 'a4'])],
            'layout.font_size' => ['required', 'string', Rule::in(['sm', 'md', 'lg'])],
            'layout.canvas_height' => 'required|integer|min:80|max:20000',
            'layout.blocks' => 'array',
            'layout.blocks.*.id' => 'required|string|max:64',
            'layout.blocks.*.type' => ['required', 'string', Rule::in($blockTypes)],
            'layout.blocks.*.enabled' => 'required|boolean',
            'layout.blocks.*.locked' => 'boolean',
            'layout.blocks.*.config' => 'array',
            'layout.blocks.*.style' => 'array',
            'layout.blocks.*.style.align' => ['nullable', 'string', Rule::in(['left', 'center', 'right'])],
            'layout.blocks.*.style.bold' => 'boolean',
            'layout.blocks.*.style.italic' => 'boolean',
            'layout.blocks.*.style.underline' => 'boolean',
            'layout.blocks.*.style.size_override' => ['nullable', 'string', Rule::in(['sm', 'md', 'lg'])],
            'layout.blocks.*.style.color' => 'nullable|string|max:32',
            'layout.blocks.*.style.background' => 'nullable|string|max:32',
            'layout.blocks.*.style.padding' => 'integer|min:0|max:80',
            'layout.blocks.*.style.border_width' => 'integer|min:0|max:20',
            'layout.blocks.*.style.border_color' => 'nullable|string|max:32',
            'layout.blocks.*.style.border_radius' => 'integer|min:0|max:80',
            'layout.blocks.*.position' => 'required|array',
            'layout.blocks.*.position.x' => 'required|integer|min:-2000|max:2000',
            'layout.blocks.*.position.y' => 'required|integer|min:-2000|max:20000',
            'layout.blocks.*.position.w' => 'required|integer|min:1|max:2000',
            'layout.blocks.*.position.h' => 'nullable|integer|min:1|max:20000',
            'layout.blocks.*.position.z' => 'integer|min:0|max:9999',
        ];
    }
}
