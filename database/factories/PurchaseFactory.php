<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Company;
use App\Models\Journal;
use App\Models\Partner;
use App\Models\Purchase;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Purchase>
 */
final class PurchaseFactory extends Factory
{
    protected $model = Purchase::class;

    public function definition(): array
    {
        return [
            'serie' => 'F001',
            'correlative' => str_pad((string) fake()->unique()->numberBetween(1, 999999), 8, '0', STR_PAD_LEFT),
            'journal_id' => Journal::factory()->purchase(),
            'date' => now(),
            'partner_id' => Partner::factory()->supplier(),
            'warehouse_id' => Warehouse::factory(),
            'company_id' => Company::factory(),
            'buyer_id' => User::factory(),
            'total' => fake()->randomFloat(2, 50, 5000),
            'observation' => null,
            'status' => 'draft',
            'payment_status' => 'unpaid',
            'vendor_bill_number' => null,
            'vendor_bill_date' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => 'draft']);
    }

    public function posted(): static
    {
        return $this->state(fn () => ['status' => 'posted']);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => 'cancelled']);
    }

    public function paid(): static
    {
        return $this->state(fn () => ['payment_status' => 'paid']);
    }
}
