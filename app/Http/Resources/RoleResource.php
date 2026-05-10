<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\Permission\Models\Role;

/**
 * @mixin Role
 */
final class RoleResource extends JsonResource
{
    /**
     * Roles creados por el seeder. Protegidos contra delete/rename.
     */
    public const SYSTEM_ROLES = [
        'admin',
        'manager',
        'almacen',
        'cajero',
        'ventas',
        'editor',
        'web_editor',
        'lector',
        'viewer',
        'superadmin',
        'user',
    ];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'guard_name' => $this->guard_name,
            'is_system' => in_array($this->name, self::SYSTEM_ROLES, true),
            'permissions' => $this->whenLoaded(
                'permissions',
                fn () => $this->permissions->pluck('name')->all(),
            ),
            'users_count' => $this->when(
                isset($this->users_count),
                fn () => (int) $this->users_count,
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
