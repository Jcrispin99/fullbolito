<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tax;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tax>
 */
final class TaxFactory extends Factory
{
    protected $model = Tax::class;

    public function definition(): array
    {
        return [
            'name' => 'IGV '.fake()->unique()->randomNumber(4),
            'description' => 'Impuesto General a las Ventas',
            'invoice_label' => 'IGV',
            'tax_type' => 'IGV',
            'affectation_type_code' => '10',
            'rate_percent' => 18.00,
            'is_price_inclusive' => false,
            'is_active' => true,
            'is_default' => false,
        ];
    }

    public function igv(): static
    {
        return $this->state(fn () => [
            'tax_type' => 'IGV',
            'rate_percent' => 18.00,
            'affectation_type_code' => '10',
        ]);
    }

    public function exempt(): static
    {
        return $this->state(fn () => [
            'tax_type' => 'EXO',
            'rate_percent' => 0.00,
            'affectation_type_code' => '20',
        ]);
    }

    public function default(): static
    {
        return $this->state(fn () => ['is_default' => true]);
    }
}
