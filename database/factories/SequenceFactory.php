<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Sequence;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sequence>
 */
final class SequenceFactory extends Factory
{
    protected $model = Sequence::class;

    public function definition(): array
    {
        return [
            'prefix' => fake()->randomElement(['B', 'F', 'T', 'M', 'NC']).'001',
            'sequence_size' => 8,
            'step' => 1,
            'next_number' => 1,
        ];
    }
}
