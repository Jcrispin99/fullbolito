<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\BillingCredential;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BillingCredential>
 */
final class BillingCredentialFactory extends Factory
{
    protected $model = BillingCredential::class;

    public function definition(): array
    {
        return [
            'name' => 'Credenciales SUNAT (Test)',
            'sol_user' => 'MODDATOS',
            'sol_pass' => 'MODDATOS',
            'cert_path' => 'billing/certs/test.pem',
            'client_id' => null,
            'client_secret' => null,
            'production' => false,
            'is_active' => true,
        ];
    }

    public function production(): static
    {
        return $this->state(fn () => ['production' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
