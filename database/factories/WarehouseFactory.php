<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Company;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Warehouse>
 */
final class WarehouseFactory extends Factory
{
    protected $model = Warehouse::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company().' Warehouse',
            'location' => fake()->city(),
            'ubigeo' => '150101',
            'address_line' => fake()->streetAddress(),
            'establishment_code' => '0000',
            'is_active' => true,
            'company_id' => Company::factory(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
