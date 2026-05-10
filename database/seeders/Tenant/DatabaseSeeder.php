<?php

declare(strict_types=1);

namespace Database\Seeders\Tenant;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Aseguramos el orden correcto de dependencias
        $this->call([
            CompanySeeder::class,
            WarehouseSeeder::class,
            UserSeeder::class,
            RolesSeeder::class,
            CategorySeeder::class,
            AttributeSeeder::class,
            UnitOfMeasureSeeder::class,
            TaxSeeder::class,
            PartnerSeeder::class,
            ProductSeeder::class,
            // MembershipPlanSeeder::class,
            JournalSeeder::class,
            LotSeeder::class,
            PaymentMethodSeeder::class,
            BillingCredentialSeeder::class,
            PosConfigSeeder::class,
            PosSessionSeeder::class,
            //PurchaseSeeder::class,
            //SaleSeeder::class,
            MovementSeeder::class,
            TransferSeeder::class,
            LoyaltySeeder::class,
            SiteSeeder::class,
        ]);
    }
}
