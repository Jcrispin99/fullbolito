<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ProductProduct;
use App\Models\ProductTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductProduct>
 */
final class ProductProductFactory extends Factory
{
    protected $model = ProductProduct::class;

    public function definition(): array
    {
        return [
            'product_template_id' => ProductTemplate::factory(),
            'sku' => 'SKU-'.fake()->unique()->numerify('######'),
            'barcode' => null,
            'price' => fake()->randomFloat(2, 1, 500),
            'cost_price' => fake()->randomFloat(2, 1, 200),
            'is_principal' => true,
        ];
    }

    public function principal(): static
    {
        return $this->state(fn () => ['is_principal' => true]);
    }

    public function variant(): static
    {
        return $this->state(fn () => ['is_principal' => false]);
    }
}
