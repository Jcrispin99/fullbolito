<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Company;
use App\Models\Court;
use App\Models\Journal;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reservation>
 */
final class ReservationFactory extends Factory
{
    protected $model = Reservation::class;

    public function definition(): array
    {
        $start = now()->addDay()->setTime(10, 0);

        return [
            'journal_id' => Journal::factory(),
            'serie' => 'RES',
            'correlative' => fake()->unique()->numerify('########'),
            'court_id' => Court::factory(),
            'company_id' => Company::factory(),
            'start_at' => $start,
            'end_at' => (clone $start)->addHour(),
            'status' => 'held',
            'held_until' => now()->addMinutes(10),
            'partner_id' => null,
            'customer_name' => fake()->name(),
            'customer_phone' => fake()->numerify('9########'),
            'customer_email' => null,
            'total' => 50,
            'notes' => null,
            'sale_id' => null,
            'created_by_user_id' => null,
        ];
    }

    public function held(): static
    {
        return $this->state(fn () => [
            'status' => 'held',
            'held_until' => now()->addMinutes(10),
        ]);
    }

    public function expiredHold(): static
    {
        return $this->state(fn () => [
            'status' => 'held',
            'held_until' => now()->subMinute(),
        ]);
    }

    public function confirmed(): static
    {
        return $this->state(fn () => [
            'status' => 'confirmed',
            'held_until' => null,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => 'cancelled',
            'held_until' => null,
            'cancelled_at' => now(),
            'cancellation_reason' => 'Test',
        ]);
    }
}
