<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courts', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('slug');
            $table->string('code')->nullable();
            $table->text('description')->nullable();

            // Deporte y características físicas
            $table->string('sport');
            $table->string('surface')->nullable();
            $table->unsignedSmallInteger('capacity')->nullable();

            // Duración por defecto del slot reservable (minutos)
            $table->unsignedSmallInteger('slot_duration_minutes')->default(60);

            // Sede física donde está la cancha
            $table->foreignId('warehouse_id')->constrained('warehouses')->onDelete('cascade');

            // Servicio facturable (1:1 con la cancha)
            $table->foreignId('product_product_id')
                ->unique()
                ->constrained('product_products')
                ->onDelete('cascade');

            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(['company_id', 'slug']);
            $table->index(['company_id', 'is_active']);
            $table->index('sport');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courts');
    }
};
