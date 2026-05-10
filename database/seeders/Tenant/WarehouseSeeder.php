<?php

namespace Database\Seeders\Tenant;

use App\Models\Company;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class WarehouseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $company = Company::first();

        if (!$company) {
            $this->command->error('No company found to assign warehouse.');
            return;
        }

        Warehouse::firstOrCreate(
            ['name' => 'Almacén Principal'],
            [
                'location' => $company->address,
                'company_id' => $company->id,
            ]
        );

        Warehouse::firstOrCreate(
            ['name' => 'Almacén Secundario'],
            [
                'location' => 'Dirección Secundaria, Lima',
                'company_id' => $company->id,
            ]
        );
    }
}
