<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lots', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_product_id')
                ->constrained('product_products')
                ->cascadeOnDelete();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->string('lot_number', 50);

            $table->date('manufactured_at')->nullable();
            $table->date('expires_at')->nullable();

            $table->foreignId('supplier_id')
                ->nullable()
                ->constrained('partners')
                ->nullOnDelete();

            $table->foreignId('purchase_id')
                ->nullable()
                ->constrained('purchases')
                ->nullOnDelete();

            $table->decimal('initial_quantity', 12, 4)->default(0);
            $table->decimal('initial_cost', 12, 4)->default(0);

            $table->enum('status', ['active', 'blocked', 'expired', 'depleted'])->default('active');

            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['product_product_id', 'lot_number']);
            $table->index(['product_product_id', 'expires_at'], 'lots_product_fefo_idx');
            $table->index(['status', 'expires_at']);
            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lots');
    }
};
