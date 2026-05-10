<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Company;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transfer>
 */
final class TransferFactory extends Factory
{
    protected $model = Transfer::class;

    public function definition(): array
    {
        return [
            'serie' => 'T001',
            'correlative' => str_pad((string) fake()->unique()->numberBetween(1, 999999), 8, '0', STR_PAD_LEFT),
            'date' => now(),
            'company_id' => Company::factory(),
            'created_user_id' => User::factory(),
            'total' => fake()->randomFloat(4, 10, 1000),
            'observation' => null,
            // GRE
            'gre_motive_code' => null,
            'gre_modality' => null,
            'gre_transfer_start_date' => null,
            'gre_gross_weight' => null,
            'gre_packages' => null,
            'gre_vehicle_plate' => null,
            'gre_driver_doc_type' => null,
            'gre_driver_doc_number' => null,
            'gre_driver_license' => null,
            'gre_driver_name' => null,
            'gre_status' => null,
            'gre_ticket' => null,
            'gre_response' => null,
            'gre_sent_at' => null,
            'gre_signed_xml_path' => null,
            'gre_cdr_zip_path' => null,
        ];
    }

    public function withGreData(): static
    {
        return $this->state(fn () => [
            'gre_motive_code' => '04',
            'gre_modality' => '02',
            'gre_transfer_start_date' => now()->toDateString(),
            'gre_gross_weight' => 12.500,
            'gre_packages' => 2,
            'gre_vehicle_plate' => 'ABC-123',
            'gre_driver_doc_type' => '1',
            'gre_driver_doc_number' => '12345678',
            'gre_driver_license' => 'Q12345678',
            'gre_driver_name' => 'Juan Pérez',
        ]);
    }
}
