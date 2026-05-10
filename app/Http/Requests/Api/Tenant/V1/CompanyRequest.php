<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Tenant\V1;

use App\Rules\UbigeoExists;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $company = $this->route('company');
        $companyId = $company ? $company->id : null;

        return [
            'business_name' => ['required', 'string', 'max:255'],
            'trade_name' => ['nullable', 'string', 'max:255'],
            'ruc' => ['required', 'string', 'max:11'],
            'address' => ['nullable', 'string', 'max:1000'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'string', 'email', 'max:100'],
            'ubigeo' => ['nullable', 'string', 'size:6', new UbigeoExists()],
            'is_active' => ['sometimes', 'boolean'],
            'parent_id' => ['nullable', 'integer', 'exists:companies,id'],
            'branch_code' => [
                'nullable',
                'string',
                'max:10',
                Rule::unique('companies', 'branch_code')->ignore($companyId),
            ],
            'is_main' => ['sometimes', 'boolean'],
        ];
    }
}
