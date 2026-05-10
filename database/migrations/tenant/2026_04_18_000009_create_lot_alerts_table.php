<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lot_alerts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('lot_id')
                ->constrained('lots')
                ->cascadeOnDelete();

            $table->foreignId('product_product_id')
                ->constrained('product_products')
                ->cascadeOnDelete();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            // expiring | expired | blocked
            $table->enum('alert_type', ['expiring', 'expired', 'blocked']);

            // Días faltantes para vencer al momento del alert (negativo si ya venció).
            $table->integer('days_until_expiry')->nullable();

            // Fecha de generación (para dedupe diario).
            $table->date('alert_date');

            $table->string('message', 255);

            // unread | read
            $table->enum('status', ['unread', 'read'])->default('unread');

            $table->timestamp('read_at')->nullable();

            $table->timestamps();

            // Evita duplicar la misma alerta el mismo día para el mismo lote y tipo.
            $table->unique(['lot_id', 'alert_type', 'alert_date'], 'lot_alerts_dedupe_unique');
            $table->index(['company_id', 'status']);
            $table->index(['product_product_id', 'alert_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lot_alerts');
    }
};
