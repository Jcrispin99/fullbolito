<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Company;
use App\Models\Journal;
use App\Models\Partner;
use App\Models\Sale;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sale>
 */
final class SaleFactory extends Factory
{
    protected $model = Sale::class;

    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 10, 500);
        $tax = round($subtotal * 0.18, 2);

        return [
            'serie' => 'B001',
            'correlative' => str_pad((string) fake()->unique()->numberBetween(1, 999999), 8, '0', STR_PAD_LEFT),
            'journal_id' => Journal::factory()->sale(),
            'date' => now(),
            'partner_id' => Partner::factory()->customer(),
            'warehouse_id' => Warehouse::factory(),
            'company_id' => Company::factory(),
            'original_sale_id' => null,
            'pos_session_id' => null,
            'user_id' => User::factory(),
            'subtotal' => $subtotal,
            'tax_amount' => $tax,
            'total' => $subtotal + $tax,
            'status' => 'posted',
            'payment_status' => 'paid',
            'sunat_status' => 'pending',
            'sunat_response' => null,
            'sunat_sent_at' => null,
            'signed_xml_path' => null,
            'cdr_zip_path' => null,
            'notes' => null,
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

    public function unpaid(): static
    {
        return $this->state(fn () => ['payment_status' => 'unpaid']);
    }

    public function sunatAccepted(): static
    {
        return $this->state(fn () => [
            'sunat_status' => 'accepted',
            'sunat_sent_at' => now(),
        ]);
    }
}
