<?php

namespace Database\Seeders\Tenant;

use App\Models\Partner;
use Illuminate\Database\Seeder;

class PartnerSeeder extends Seeder
{
    /**
     * Run the database seeder.
     */
    public function run(): void
    {
        // ========================================
        // CUSTOMER DEFAULT (Cliente Varios)
        // ========================================

        Partner::firstOrCreate(
            [
                'document_type' => 'DNI',
                'document_number' => '00000000',
            ],
            [
                'is_customer' => true,
                'is_supplier' => false,
                'name' => 'Varios',
                'email' => 'varios@krakengym.com',
                'status' => 'active',
            ]
        );

        Partner::firstOrCreate(
            [
                'document_type' => 'RUC',
                'document_number' => '20123456789',
            ],
            [
                'is_customer' => false,
                'is_supplier' => true,
                'name' => 'Proveedor Principal SAC',
                'email' => 'proveedor@example.com',
                'status' => 'active',
            ]
        );
    }
}
