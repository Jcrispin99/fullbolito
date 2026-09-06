<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Company;
use App\Models\Court;
use App\Models\ProductProduct;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Court>
 */
final class CourtFactory extends Factory
{
    protected $model = Court::class;

    public function definition(): array
    {
        $name = 'Cancha '.fake()->unique()->numerify('####');

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'code' => null,
            'description' => null,
            'sport' => fake()->randomElement(['futbol7', 'futsal', 'padel', 'voley']),
            'surface' => null,
            'capacity' => 10,
            'slot_duration_minutes' => 60,
            'product_product_id' => ProductProduct::factory()->principal(),
            'company_id' => Company::factory(),
            'is_active' => true,
        ];
    }
}
