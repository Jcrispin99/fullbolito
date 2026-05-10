<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Tenant\V1;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $user = $this->route('user');
        $userId = $user instanceof User ? $user->id : null;

        $emailUnique = Rule::unique('users', 'email');
        if ($userId) {
            $emailUnique = $emailUnique->ignore($userId);
        }

        $passwordRules = $this->isMethod('post')
            ? ['required', 'string', 'min:8', 'confirmed']
            : ['nullable', 'string', 'min:8', 'confirmed'];

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', $emailUnique],
            'password' => $passwordRules,
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')->where('guard_name', 'web')],
            'company_ids' => ['nullable', 'array'],
            'company_ids.*' => ['integer', Rule::exists('companies', 'id')],
        ];
    }
}
