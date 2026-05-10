<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentMethod>
 */
final class PaymentMethodFactory extends Factory
{
    protected $model = PaymentMethod::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement(['Efectivo', 'Tarjeta', 'Yape', 'Plin', 'Transferencia']),
            'is_active' => true,
        ];
    }

    public function cash(): static
    {
        return $this->state(fn () => ['name' => 'Efectivo']);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
