<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\UnitOfMeasure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UnitOfMeasure>
 */
final class UnitOfMeasureFactory extends Factory
{
    protected $model = UnitOfMeasure::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement([
                'Unidad', 'Kilogramo', 'Litro', 'Metro', 'Caja', 'Paquete', 'Docena',
            ]),
            'symbol' => fake()->unique()->randomElement(['UND', 'KGM', 'LTR', 'MTR', 'BX', 'PKG', 'DZN']),
            'family' => 'count',
            'base_unit_id' => null,
            'factor' => 1,
            'is_active' => true,
        ];
    }

    public function unit(): static
    {
        return $this->state(fn () => [
            'name' => 'Unidad',
            'symbol' => 'UND',
            'family' => 'count',
        ]);
    }
}
