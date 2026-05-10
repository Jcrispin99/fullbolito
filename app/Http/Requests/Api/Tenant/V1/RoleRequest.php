<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Tenant\V1;

use App\Http\Resources\RoleResource;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

final class RoleRequest extends FormRequest
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
        $role = $this->route('role');
        $roleId = $role instanceof Role ? $role->id : null;

        $nameRules = ['required', 'string', 'max:125', 'regex:/^[a-z0-9_]+$/'];

        $nameUnique = Rule::unique('roles', 'name')->where('guard_name', 'web');
        if ($roleId) {
            $nameUnique = $nameUnique->ignore($roleId);
        }
        $nameRules[] = $nameUnique;

        return [
            'name' => $nameRules,
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')->where('guard_name', 'web')],
        ];
    }

    public function messages(): array
    {
        return [
            'name.regex' => 'El nombre del rol solo puede contener minúsculas, números y guion bajo (ej: jefe_almacen).',
        ];
    }

    /**
     * Bloquea rename de roles del sistema.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $role = $this->route('role');
            if (! $role instanceof Role) {
                return;
            }

            $newName = $this->input('name');
            if (
                in_array($role->name, RoleResource::SYSTEM_ROLES, true)
                && is_string($newName)
                && $newName !== $role->name
            ) {
                $v->errors()->add('name', 'No se puede renombrar un rol del sistema.');
            }
        });
    }
}
