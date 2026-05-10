<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Tenant\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class LoyaltyProgramRequest extends FormRequest
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
            'program_type' => ['required', Rule::in(['promotion', 'coupon', 'loyalty', 'buy_x_get_y', 'promo_code'])],
            'is_pos' => ['sometimes', 'boolean'],
            'is_web' => ['sometimes', 'boolean'],
            'is_sales' => ['sometimes', 'boolean'],
            'applies_on' => ['sometimes', Rule::in(['current', 'future', 'both'])],
            'trigger' => ['sometimes', Rule::in(['auto', 'with_code'])],
            'point_name' => ['sometimes', 'string', 'max:50'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'max_uses_per_customer' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],

            // Nested rules
            'rules' => ['sometimes', 'array'],
            'rules.*.reward_point_amount' => ['required', 'numeric', 'min:0.01'],
            'rules.*.reward_point_mode' => ['required', Rule::in(['order', 'money', 'unit'])],
            'rules.*.minimum_qty' => ['sometimes', 'integer', 'min:0'],
            'rules.*.minimum_amount' => ['sometimes', 'numeric', 'min:0'],
            'rules.*.code' => ['nullable', 'string', 'max:50'],
            'rules.*.conditions' => ['nullable', 'array'],
            'rules.*.product_variant_ids' => ['nullable', 'array'],
            'rules.*.product_variant_ids.*' => ['integer', 'exists:product_products,id'],
            'rules.*.product_template_ids' => ['nullable', 'array'],
            'rules.*.product_template_ids.*' => ['integer', 'exists:product_templates,id'],
            'rules.*.category_ids' => ['nullable', 'array'],
            'rules.*.category_ids.*' => ['integer', 'exists:categories,id'],

            // Nested rewards
            'rewards' => ['sometimes', 'array'],
            'rewards.*.reward_type' => ['required', Rule::in(['discount', 'product'])],
            'rewards.*.required_points' => ['required', 'numeric', 'min:0'],
            'rewards.*.description' => ['nullable', 'string', 'max:500'],
            'rewards.*.discount' => ['nullable', 'numeric', 'min:0'],
            'rewards.*.discount_mode' => ['nullable', Rule::in(['percent', 'fixed_amount', 'per_point'])],
            'rewards.*.discount_applicability' => ['nullable', Rule::in(['order', 'cheapest', 'specific'])],
            'rewards.*.discount_max_amount' => ['nullable', 'numeric', 'min:0'],
            'rewards.*.reward_product_id' => ['nullable', 'integer', 'exists:product_products,id'],
            'rewards.*.reward_product_qty' => ['sometimes', 'integer', 'min:1'],
            'rewards.*.discount_product_ids' => ['nullable', 'array'],
            'rewards.*.discount_product_ids.*' => ['integer', 'exists:product_products,id'],
            'rewards.*.discount_category_ids' => ['nullable', 'array'],
            'rewards.*.discount_category_ids.*' => ['integer', 'exists:categories,id'],
        ];

        if ($this->isMethod('put') || $this->isMethod('patch')) {
            $rules = array_map(function ($rule) {
                if (is_array($rule) && ($rule[0] ?? null) === 'required') {
                    $rule[0] = 'sometimes';
                }

                return $rule;
            }, $rules);
        }

        return $rules;
    }
}
