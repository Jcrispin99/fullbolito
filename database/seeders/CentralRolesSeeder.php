<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class CentralRolesSeeder extends Seeder
{
    /**
     * Permisos del dominio central. Derivados de routes/api/central/v1.php.
     *
     * @return list<string>
     */
    private function permissions(): array
    {
        return [
            'manage_plans',
            'manage_modules',
            'manage_tenants',
            'manage_users',
            'manage_subscriptions',
            'read_activity',
        ];
    }

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->permissions() as $name) {
            Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]);
        }

        Permission::where('guard_name', 'web')
            ->whereNotIn('name', $this->permissions())
            ->delete();

        Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web'])
            ->syncPermissions(Permission::all());

        Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web'])
            ->syncPermissions([]);
    }
}
