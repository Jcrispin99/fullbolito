<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `loyalty_transactions.points`/`balance` se crearon como `integer`, pero los
 * modelos los castean como `decimal:2` (igual que `loyalty_cards.points`).
 * Reglas con `reward_point_mode = money` pueden generar puntos con decimales
 * (ej. 0.03 puntos por sol), que se truncaban al guardarse. Se alinea el tipo
 * de columna con el cast del modelo y con `loyalty_cards.points`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loyalty_transactions', function (Blueprint $table) {
            $table->decimal('points', 12, 2)->change();
            $table->decimal('balance', 12, 2)->change();
        });
    }

    public function down(): void
    {
        Schema::table('loyalty_transactions', function (Blueprint $table) {
            $table->integer('points')->change();
            $table->integer('balance')->change();
        });
    }
};
