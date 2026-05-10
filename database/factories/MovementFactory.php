<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Company;
use App\Models\Movement;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Movement>
 */
final class MovementFactory extends Factory
{
    protected $model = Movement::class;

    public function definition(): array
    {
        return [
            'type' => 'entry',
            'serie' => 'M001',
            'correlative' => str_pad((string) fake()->unique()->numberBetween(1, 999999), 8, '0', STR_PAD_LEFT),
            'date' => now(),
            'total' => fake()->randomFloat(4, 10, 1000),
            'observation' => null,
            'reason' => null,
            'warehouse_id' => Warehouse::factory(),
            'company_id' => Company::factory(),
            'journal_id' => null,
            'transfer_id' => null,
            'status' => 'draft',
            'submitted_at' => null,
            'posted_at' => null,
            'rejected_at' => null,
            'cancelled_at' => null,
            'created_user_id' => User::factory(),
            'submitted_user_id' => null,
            'posted_user_id' => null,
            'rejected_user_id' => null,
            'cancelled_user_id' => null,
            'rejection_reason' => null,
        ];
    }

    public function entry(): static
    {
        return $this->state(fn () => ['type' => 'entry']);
    }

    public function exit(): static
    {
        return $this->state(fn () => ['type' => 'exit']);
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => 'draft']);
    }

    public function submitted(): static
    {
        return $this->state(fn () => [
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);
    }

    public function posted(): static
    {
        return $this->state(fn () => [
            'status' => 'posted',
            'submitted_at' => now()->subMinute(),
            'posted_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'status' => 'rejected',
            'rejected_at' => now(),
            'rejection_reason' => 'Test rejection',
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);
    }
}
