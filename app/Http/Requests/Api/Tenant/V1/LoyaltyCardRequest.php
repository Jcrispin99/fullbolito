<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Tenant\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class LoyaltyCardRequest extends FormRequest
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
        return [
            'loyalty_program_id' => ['required', 'integer', Rule::exists('loyalty_programs', 'id')],
            'partner_id' => ['nullable', 'integer', Rule::exists('partners', 'id')],
            'points' => ['sometimes', 'numeric', 'min:0'],
            'expiration_date' => ['nullable', 'date'],
        ];
    }
}
