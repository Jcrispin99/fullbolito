<?php

declare(strict_types=1);

namespace Database\Seeders\Tenant;

use App\Models\PosConfig;
use App\Models\PosSession;
use App\Models\User;
use Illuminate\Database\Seeder;

final class PosSessionSeeder extends Seeder
{
    public function run(): void
    {
        $posConfig = PosConfig::first();
        $user = User::first();

        if (! $posConfig || ! $user) {
            $this->command->error('No hay PosConfig o Users. Ejecuta sus seeders primero.');
            return;
        }

        // Sesión cerrada de ayer
        PosSession::firstOrCreate(
            [
                'pos_config_id' => $posConfig->id,
                'user_id' => $user->id,
                'status' => PosSession::STATUS_CLOSED,
            ],
            [
                'opening_balance' => 100.00,
                'closing_balance' => 850.00,
                'opening_note' => 'Apertura con fondo de caja estándar',
                'closing_note' => 'Cierre sin novedad',
                'opened_at' => now()->subDay()->startOfDay()->addHours(8),
                'closed_at' => now()->subDay()->startOfDay()->addHours(20),
            ]
        );

        // Sesión abierta hoy
        PosSession::firstOrCreate(
            [
                'pos_config_id' => $posConfig->id,
                'user_id' => $user->id,
                'status' => PosSession::STATUS_OPENED,
            ],
            [
                'opening_balance' => 100.00,
                'closing_balance' => 0,
                'opening_note' => 'Apertura turno mañana',
                'opened_at' => now()->startOfDay()->addHours(8),
            ]
        );

        $this->command->info('Se crearon 2 sesiones POS (1 cerrada, 1 abierta).');
    }
}
