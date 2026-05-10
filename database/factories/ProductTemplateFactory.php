<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Category;
use App\Models\ProductTemplate;
use App\Models\UnitOfMeasure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductTemplate>
 */
final class ProductTemplateFactory extends Factory
{
    protected $model = ProductTemplate::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->sentence(),
            'price' => fake()->randomFloat(2, 1, 500),
            'category_id' => Category::factory(),
            'uom_id' => UnitOfMeasure::factory()->unit(),
            'is_active' => true,
            'is_pos_visible' => true,
            'tracks_inventory' => true,
            'is_service' => false,
            'tracked_by_lot' => false,
            'expiration_alert_days' => null,
            'expiration_block_days' => null,
        ];
    }

    public function service(): static
    {
        return $this->state(fn () => [
            'is_service' => true,
            'tracks_inventory' => false,
        ]);
    }

    public function trackedByLot(): static
    {
        return $this->state(fn () => [
            'tracked_by_lot' => true,
            'expiration_alert_days' => 30,
            'expiration_block_days' => 0,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
