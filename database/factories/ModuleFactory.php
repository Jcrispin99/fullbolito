<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Module;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Module>
 */
final class ModuleFactory extends Factory
{
    protected $model = Module::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $key = Str::slug(fake()->unique()->word());

        return [
            'key' => $key,
            'label' => Str::headline($key),
            'description' => fake()->sentence(),
            'icon' => null,
            'addon_price' => 0,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function addon(float $price = 9.99): static
    {
        return $this->state(fn () => [
            'addon_price' => $price,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
