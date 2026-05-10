<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Central\V1;

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
        $userId = $this->route('user')?->id;

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
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')->where('guard_name', 'web')],
            'password' => $passwordRules,
        ];
    }
}

