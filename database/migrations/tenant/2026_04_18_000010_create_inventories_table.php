<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->string('detail')->nullable();

            $table->decimal('quantity_in', 12, 4)->default(0);
            $table->decimal('cost_in', 12, 4)->default(0);
            $table->decimal('total_in', 12, 4)->default(0);

            $table->decimal('quantity_out', 12, 4)->default(0);
            $table->decimal('cost_out', 12, 4)->default(0);
            $table->decimal('total_out', 12, 4)->default(0);

            $table->decimal('quantity_balance', 12, 4)->default(0);
            $table->decimal('cost_balance', 12, 4)->default(0);
            $table->decimal('total_balance', 12, 4)->default(0);

            $table->foreignId('product_product_id')->constrained('product_products')->onDelete('cascade');
            $table->foreignId('warehouse_id')->constrained()->onDelete('cascade');
            $table->foreignId('lot_id')->nullable()->constrained('lots')->nullOnDelete();
            $table->morphs('inventoryable');

            $table->timestamps();

            $table->index(['product_product_id', 'warehouse_id', 'created_at'], 'inventory_lookup');
            $table->index(['lot_id', 'warehouse_id'], 'inventories_lot_warehouse_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventories');
    }
};
