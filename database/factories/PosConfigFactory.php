<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Company;
use App\Models\PosConfig;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PosConfig>
 */
final class PosConfigFactory extends Factory
{
    protected $model = PosConfig::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => 'Caja '.fake()->unique()->numerify('##'),
            'warehouse_id' => Warehouse::factory(),
            'default_customer_id' => null,
            'tax_id' => null,
            'apply_tax' => true,
            'prices_include_tax' => false,
            'is_active' => true,
            'default_lot_strategy' => 'fefo_suggest_manual',
            'allow_expired_sale_with_override' => false,
            'lot_scan_mode' => 'product_only',
        ];
    }
}
