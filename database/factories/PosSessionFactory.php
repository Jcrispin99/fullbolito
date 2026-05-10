<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PosConfig;
use App\Models\PosSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PosSession>
 */
final class PosSessionFactory extends Factory
{
    protected $model = PosSession::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'pos_config_id' => PosConfig::factory(),
            'opening_balance' => 100.00,
            'opening_note' => null,
            'closing_balance' => null,
            'closing_note' => null,
            'opened_at' => now(),
            'closed_at' => null,
            'status' => PosSession::STATUS_OPENED,
        ];
    }

    public function opened(): static
    {
        return $this->state(fn () => [
            'status' => PosSession::STATUS_OPENED,
            'opened_at' => now(),
            'closed_at' => null,
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn () => [
            'status' => PosSession::STATUS_CLOSED,
            'closing_balance' => 0.00,
            'closed_at' => now(),
        ]);
    }
}
