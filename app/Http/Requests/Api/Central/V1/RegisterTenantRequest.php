<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Central\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property string $business_name
 * @property string $phone
 * @property string $password
 * @property string|null $plan_slug
 */
final class RegisterTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'business_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:32'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'plan_slug' => [
                'nullable',
                'string',
                Rule::exists('plans', 'slug')->where(fn ($q) => $q->where('is_active', true)),
            ],
        ];
    }
}

