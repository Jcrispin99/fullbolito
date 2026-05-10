<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        if (tenancy()->initialized) {
            $this->call(TenantSeeder::class);

            return;
        }

        $this->call(PlanSeeder::class);
        $this->call(ModuleSeeder::class);
        $this->call(SiteBlockCatalogSeeder::class);
        $this->call(CentralRolesSeeder::class);

        $admin = User::factory()->create([
            'name' => 'Central Admin',
            'email' => 'admin@gmail.com',
            'password' => bcrypt('password'),
        ]);

        $admin->syncRoles(['superadmin']);
    }
}
