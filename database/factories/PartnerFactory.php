<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Company;
use App\Models\Partner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Partner>
 */
final class PartnerFactory extends Factory
{
    protected $model = Partner::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'user_id' => null,
            'is_customer' => true,
            'is_supplier' => false,
            'document_type' => 'DNI',
            'document_number' => (string) fake()->unique()->numerify('########'),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('9########'),
            'address' => fake()->streetAddress(),
            'ubigeo' => '150101',
            'status' => 'active',
        ];
    }

    public function customer(): static
    {
        return $this->state(fn () => ['is_customer' => true, 'is_supplier' => false]);
    }

    public function supplier(): static
    {
        return $this->state(fn () => [
            'is_customer' => false,
            'is_supplier' => true,
            'document_type' => 'RUC',
            'document_number' => (string) fake()->unique()->numerify('20#########'),
        ]);
    }

    public function dual(): static
    {
        return $this->state(fn () => ['is_customer' => true, 'is_supplier' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => 'inactive']);
    }
}
