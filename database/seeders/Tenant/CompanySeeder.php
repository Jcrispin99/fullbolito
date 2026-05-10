<?php

declare(strict_types=1);

namespace Database\Seeders\Tenant;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = (string) tenant('id');

        $main = Company::query()->updateOrCreate(
            ['branch_code' => 'MAIN'],
            [
                'business_name' => mb_strtoupper($tenantId) . ' SAC',
                'trade_name' => mb_strtoupper($tenantId),
                'ruc' => '20614550440',
                'address' => 'Av. Principal 123',
                'phone' => '999888777',
                'email' => 'admin@' . $tenantId . '.com',
                'ubigeo' => '150101',
                'is_active' => true,
                'parent_id' => null,
                'is_main' => true,
            ],
        );

        Company::query()->updateOrCreate(
            ['branch_code' => 'BR001'],
            [
                'business_name' => $main->business_name . ' - Sucursal 01',
                'trade_name' => $main->trade_name,
                'ruc' => '20614550440',
                'address' => 'Av. Secundaria 456',
                'phone' => '999888778',
                'email' => 'branch1@' . $tenantId . '.com',
                'ubigeo' => '150102',
                'is_active' => true,
                'parent_id' => $main->id,
                'is_main' => false,
            ],
        );

        Company::query()->updateOrCreate(
            ['branch_code' => 'BR002'],
            [
                'business_name' => $main->business_name . ' - Sucursal 02',
                'trade_name' => $main->trade_name,
                'ruc' => '20614550440',
                'address' => 'Jr. Comercio 789',
                'phone' => '999888779',
                'email' => 'branch2@' . $tenantId . '.com',
                'ubigeo' => '150103',
                'is_active' => true,
                'parent_id' => $main->id,
                'is_main' => false,
            ],
        );

        $user = User::query()->orderBy('id')->first();
        if ($user) {
            $main->users()->syncWithoutDetaching([$user->id]);
        }
    }
}
