<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
final class CompanyFactory extends Factory
{
    protected $model = Company::class;

    public function definition(): array
    {
        return [
            'business_name' => fake()->company(),
            'trade_name' => fake()->companySuffix(),
            'ruc' => (string) fake()->numerify('20#########'),
            'address' => fake()->streetAddress(),
            'phone' => fake()->numerify('9########'),
            'email' => fake()->unique()->companyEmail(),
            'ubigeo' => '150101',
            'is_active' => true,
            'is_main' => false,
            'parent_id' => null,
            'branch_code' => null,
        ];
    }

    public function main(): static
    {
        return $this->state(fn () => ['is_main' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
