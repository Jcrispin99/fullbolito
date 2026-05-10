<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Central\V1;

use App\Models\Tenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @property string|null $id
 * @property string|null $domain
 * @property list<string>|null $domains
 * @property int|null $user_id
 * @property array<string, mixed>|null $data
 */
final class TenantRequest extends FormRequest
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
        /** @var Tenant|string|null $tenant */
        $tenant = $this->route('tenant');
        $tenantId = $tenant instanceof Tenant ? $tenant->id : (is_string($tenant) ? $tenant : null);

        $isCreate = $this->isMethod('post');

        $domainUnique = Rule::unique('domains', 'domain');
        if (! $isCreate && $tenantId) {
            $domainUnique = $domainUnique->where(fn ($q) => $q->where('tenant_id', '!=', $tenantId));
        }

        return [
            'id' => $isCreate
                ? ['required', 'string', 'max:64', 'alpha_dash', 'unique:tenants,id']
                : ['sometimes', 'string', 'max:64', 'alpha_dash'],

            'domain' => $isCreate
                ? ['required_without:domains', 'string', 'max:255', $domainUnique]
                : ['sometimes', 'string', 'max:255', $domainUnique],

            'domains' => $isCreate
                ? ['required_without:domain', 'array', 'min:1']
                : ['sometimes', 'array', 'min:1'],
            'domains.*' => ['required', 'string', 'max:255', 'distinct', $domainUnique],

            'user_id' => $isCreate
                ? ['nullable', 'integer', 'exists:users,id']
                : ['sometimes', 'nullable', 'integer', 'exists:users,id'],

            'data' => $isCreate
                ? ['nullable', 'array']
                : ['sometimes', 'nullable', 'array'],
        ];
    }
}

