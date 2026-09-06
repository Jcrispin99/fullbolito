<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Court;
use App\Models\CourtSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourtSchedule>
 */
final class CourtScheduleFactory extends Factory
{
    protected $model = CourtSchedule::class;

    public function definition(): array
    {
        return [
            'court_id' => Court::factory(),
            'day_of_week' => fake()->numberBetween(0, 6),
            'start_time' => '08:00:00',
            'end_time' => '22:00:00',
            'price' => 50,
            'slot_duration_minutes' => null,
            'is_active' => true,
        ];
    }
}
