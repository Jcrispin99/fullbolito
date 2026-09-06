<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('court_schedules', function (Blueprint $table) {
            $table->id();

            $table->foreignId('court_id')->constrained('courts')->onDelete('cascade');

            // 0 = domingo, 6 = sábado (convención Carbon)
            $table->unsignedTinyInteger('day_of_week');

            $table->time('start_time');
            $table->time('end_time');

            // Precio del slot en esta franja (sobrescribe el del product_product)
            $table->decimal('price', 10, 2);

            // Override opcional de la duración del slot definida en la cancha
            $table->unsignedSmallInteger('slot_duration_minutes')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['court_id', 'day_of_week', 'is_active'], 'court_schedules_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('court_schedules');
    }
};
