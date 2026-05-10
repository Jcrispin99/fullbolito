<?php

declare(strict_types=1);

namespace Database\Seeders\Tenant;

use Illuminate\Database\Seeder;

/**
 * Siembra los datos base de un tenant nuevo.
 * Se omite UserSeeder porque el usuario ya se crea durante el registro.
 */
final class TenantBaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CompanySeeder::class,
            WarehouseSeeder::class,
            CategorySeeder::class,
            AttributeSeeder::class,
            UnitOfMeasureSeeder::class,
            TaxSeeder::class,
            PartnerSeeder::class,
            JournalSeeder::class,
            PaymentMethodSeeder::class,
            PosConfigSeeder::class,
        ]);
    }
}
