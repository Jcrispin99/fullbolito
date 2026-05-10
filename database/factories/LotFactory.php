<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Company;
use App\Models\Lot;
use App\Models\ProductProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lot>
 */
final class LotFactory extends Factory
{
    protected $model = Lot::class;

    public function definition(): array
    {
        return [
            'product_product_id' => ProductProduct::factory(),
            'company_id' => Company::factory(),
            'lot_number' => 'L-'.fake()->unique()->numerify('######'),
            'manufactured_at' => now()->subMonths(2)->toDateString(),
            'expires_at' => now()->addMonths(6)->toDateString(),
            'supplier_id' => null,
            'purchase_id' => null,
            'initial_quantity' => 100,
            'initial_cost' => 10.00,
            'status' => 'active',
            'notes' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => 'active']);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => 'expired',
            'expires_at' => now()->subDays(1)->toDateString(),
        ]);
    }

    public function blocked(): static
    {
        return $this->state(fn () => ['status' => 'blocked']);
    }

    public function depleted(): static
    {
        return $this->state(fn () => ['status' => 'depleted']);
    }

    public function expiringIn(int $days): static
    {
        return $this->state(fn () => [
            'expires_at' => now()->addDays($days)->toDateString(),
        ]);
    }

    public function noExpiration(): static
    {
        return $this->state(fn () => ['expires_at' => null]);
    }
}
