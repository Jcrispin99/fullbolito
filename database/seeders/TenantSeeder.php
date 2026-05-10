<?php

declare(strict_types=1);

namespace Database\Seeders;

use Database\Seeders\Tenant\DatabaseSeeder as TenantDatabaseSeeder;
use Illuminate\Database\Seeder;

final class TenantSeeder extends Seeder
{
    /**
     * Seed the tenant's database.
     */
    public function run(): void
    {
        $this->call(TenantDatabaseSeeder::class);
    }
}
