<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Company;
use App\Models\Journal;
use App\Models\Sequence;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Journal>
 */
final class JournalFactory extends Factory
{
    protected $model = Journal::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'code' => 'J'.fake()->unique()->numerify('####'),
            'type' => 'sale',
            'is_fiscal' => true,
            'document_type_code' => '03',
            'sequence_id' => Sequence::factory(),
            'company_id' => Company::factory(),
        ];
    }

    public function sale(): static
    {
        return $this->state(fn () => [
            'type' => 'sale',
            'document_type_code' => '03',
        ]);
    }

    public function invoice(): static
    {
        return $this->state(fn () => [
            'type' => 'sale',
            'document_type_code' => '01',
        ]);
    }

    /**
     * Nota de crédito. Por defecto afecta boletas (03). Para NC de facturas
     * encadenar ->affectsInvoice().
     */
    public function creditNote(): static
    {
        return $this->state(fn () => [
            'type' => 'sale',
            'document_type_code' => '07',
            'affects_document_type_code' => '03',
        ]);
    }

    public function affectsInvoice(): static
    {
        return $this->state(fn () => [
            'affects_document_type_code' => '01',
        ]);
    }

    public function affectsReceipt(): static
    {
        return $this->state(fn () => [
            'affects_document_type_code' => '03',
        ]);
    }

    public function purchase(): static
    {
        return $this->state(fn () => [
            'type' => 'purchase',
            'document_type_code' => null,
            'is_fiscal' => false,
        ]);
    }

    public function transfer(): static
    {
        return $this->state(fn () => [
            'type' => 'transfer',
            'document_type_code' => '09',
            'is_fiscal' => false,
        ]);
    }

    public function movement(): static
    {
        return $this->state(fn () => [
            'type' => 'movement',
            'document_type_code' => null,
            'is_fiscal' => false,
        ]);
    }

    public function movementEntry(): static
    {
        return $this->state(fn () => [
            'type' => 'movement_entry',
            'document_type_code' => null,
            'is_fiscal' => false,
        ]);
    }

    public function movementExit(): static
    {
        return $this->state(fn () => [
            'type' => 'movement_exit',
            'document_type_code' => null,
            'is_fiscal' => false,
        ]);
    }
}
