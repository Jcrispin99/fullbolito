<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lot_inventories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('lot_id')
                ->constrained('lots')
                ->cascadeOnDelete();

            $table->foreignId('warehouse_id')
                ->constrained('warehouses')
                ->cascadeOnDelete();

            $table->decimal('quantity_balance', 12, 4)->default(0);

            $table->timestamps();

            $table->unique(['lot_id', 'warehouse_id']);
            $table->index(['warehouse_id', 'quantity_balance']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lot_inventories');
    }
};
