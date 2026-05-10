<?php

declare(strict_types=1);

namespace Database\Seeders\Tenant;

use App\Models\LoyaltyCard;
use App\Models\LoyaltyProgram;
use App\Models\LoyaltyReward;
use App\Models\LoyaltyRule;
use App\Models\LoyaltyTransaction;
use App\Models\Partner;
use Illuminate\Database\Seeder;

final class LoyaltySeeder extends Seeder
{
    public function run(): void
    {
        $customers = Partner::customers()->take(5)->get();

        if ($customers->isEmpty()) {
            $this->command->warn('No hay clientes. Ejecuta PartnerSeeder primero.');
            return;
        }

        // Reset completo: arrancamos solo con el programa de acumulación POS
        LoyaltyTransaction::query()->delete();
        LoyaltyCard::query()->delete();
        LoyaltyReward::query()->delete();
        LoyaltyRule::query()->delete();
        LoyaltyProgram::query()->delete();

        // ═════════════════════════════════════════════════════════
        // Club de Puntos POS — Tipo 1: acumulación en POS
        // ═════════════════════════════════════════════════════════
        $program = LoyaltyProgram::create([
            'name' => 'Club de Puntos POS',
            'description' => 'Acumula 0.03 puntos por cada S/ 1 gastado en el POS.',
            'program_type' => 'loyalty',
            'is_pos' => true,
            'is_web' => false,
            'is_sales' => false,
            'applies_on' => 'current',
            'trigger' => 'auto',
            'point_name' => 'Puntos',
            'starts_at' => now()->subWeek(),
            'is_active' => true,
        ]);

        $program->rules()->create([
            'reward_point_amount' => 0.03,
            'reward_point_mode' => 'money',
            'minimum_amount' => 0,
        ]);

        // Canje: 1 punto = S/ 1 de descuento al total
        $program->rewards()->create([
            'reward_type' => 'discount',
            'required_points' => 1,
            'description' => '1 punto = S/ 1 de descuento al total',
            'discount' => 1.00,
            'discount_mode' => 'per_point',
            'discount_applicability' => 'order',
        ]);

        // Saldo inicial para los primeros clientes (para pruebas)
        foreach ($customers->take(3) as $index => $customer) {
            $seedPoints = match ($index) {
                0 => 18.75,
                1 => 9.40,
                default => 3.00,
            };

            $card = LoyaltyCard::create([
                'loyalty_program_id' => $program->id,
                'partner_id' => $customer->id,
                'code' => LoyaltyCard::generateCode(),
                'points' => $seedPoints,
                'is_active' => true,
            ]);

            LoyaltyTransaction::create([
                'loyalty_program_id' => $program->id,
                'loyalty_card_id' => $card->id,
                'partner_id' => $customer->id,
                'type' => 'earn',
                'points' => $seedPoints,
                'balance' => $seedPoints,
                'description' => 'Saldo inicial POS para pruebas',
            ]);
        }

        $this->command->info(
            LoyaltyProgram::count() . ' programa, '
            . LoyaltyRule::count() . ' reglas, '
            . LoyaltyCard::count() . ' tarjetas, '
            . LoyaltyTransaction::count() . ' transacciones.'
        );
    }
}
