<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courts', function (Blueprint $table) {
            // Una cancha pertenece a una sede (Company). El warehouse usado
            // para emitir la venta se resuelve en runtime desde la company.
            $table->dropForeign(['warehouse_id']);
            $table->dropColumn('warehouse_id');
        });
    }

    public function down(): void
    {
        Schema::table('courts', function (Blueprint $table) {
            $table->foreignId('warehouse_id')
                ->nullable()
                ->after('slot_duration_minutes')
                ->constrained('warehouses')
                ->onDelete('cascade');
        });
    }
};
