<?php

namespace Database\Seeders\Tenant;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeder.
     */
    public function run(): void
    {
        $mainOffice = Company::where('is_main', true)->first();
        $branches = Company::where('is_main', false)->get();

        // Usuario 1: Admin - Acceso a TODAS las compañías
        $admin = User::firstOrCreate(
            ['email' => 'admin@gmail.com'],
            ['name' => 'Admin User', 'password' => Hash::make('d4b38d89232b-64936253ff63')]
        );
        // Asignar todas las compañías
        $allCompanyIds = Company::pluck('id')->toArray();
        if ($admin && !empty($allCompanyIds)) {
            foreach ($allCompanyIds as $id) {
                $company = Company::find($id);
                if ($company && !$company->users()->where('user_id', $admin->id)->exists()) {
                    $company->users()->attach($admin->id);
                }
            }
        }

        // Usuario 2: Manager - Solo casa matriz y primera sucursal
        $manager = User::firstOrCreate(
            ['email' => 'manager@gmail.com'],
            ['name' => 'Manager User', 'password' => Hash::make('password')]
        );
        if ($manager && $mainOffice) {
            if (!$mainOffice->users()->where('user_id', $manager->id)->exists()) {
                $mainOffice->users()->attach($manager->id);
            }
            if ($branches->count() > 0 && !$branches->first()->users()->where('user_id', $manager->id)->exists()) {
                $branches->first()->users()->attach($manager->id);
            }
        }

        // Usuario 3: Branch User - Solo una sucursal
        if ($branches->count() > 0) {
            $branchUser = User::firstOrCreate(
                ['email' => 'branch@gmail.com'],
                ['name' => 'Branch User', 'password' => Hash::make('password')]
            );
            if (!$branches->first()->users()->where('user_id', $branchUser->id)->exists()) {
                $branches->first()->users()->attach($branchUser->id);
            }
        }
    }
}
