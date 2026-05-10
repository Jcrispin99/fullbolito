<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pos_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('default_customer_id')->nullable()->constrained('partners')->nullOnDelete();

            // Configuración de impuestos
            $table->foreignId('tax_id')->nullable()->constrained('taxes')->nullOnDelete();
            $table->boolean('apply_tax')->default(true);
            $table->boolean('prices_include_tax')->default(false);

            // Configuración de lotes
            $table->enum('default_lot_strategy', ['fefo_auto', 'fefo_suggest_manual', 'manual'])
                ->default('fefo_suggest_manual');
            $table->boolean('allow_expired_sale_with_override')->default(false);
            $table->enum('lot_scan_mode', ['product_only', 'hybrid'])->default('product_only');

            // Impresión
            $table->boolean('auto_print_receipt')->default(false);

            // Estado
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pos_configs');
    }
};
