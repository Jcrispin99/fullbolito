<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            // El frontend y BillingController ya manejaban descripción, pero
            // la columna nunca había sido creada en el esquema central.
            $table->text('description')->nullable()->after('slug');
            $table->unsignedTinyInteger('sunat_worker_slots')
                ->default(1)
                ->after('includes_all_modules');
            $table->boolean('sunat_dedicated_queue')
                ->default(false)
                ->after('sunat_worker_slots');
        });

        Schema::create('sunat_processing_slots', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->unsignedTinyInteger('slot_number');
            $table->uuid('owner')->nullable();
            $table->timestamp('locked_until')->nullable()->index();
            $table->timestamps();

            $table->unique(['tenant_id', 'slot_number']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        Schema::create('sunat_document_locks', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->unsignedBigInteger('sale_id');
            $table->uuid('owner')->nullable();
            $table->timestamp('locked_until')->nullable()->index();
            $table->timestamps();

            $table->unique(['tenant_id', 'sale_id']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        // Aplicar la oferta inicial también a instalaciones existentes; el
        // seeder cubre instalaciones nuevas y ejecuciones posteriores.
        DB::table('plans')->where('slug', 'free-trial')->update([
            'sunat_worker_slots' => 1,
            'sunat_dedicated_queue' => false,
        ]);
        DB::table('plans')->where('slug', 'basico-mensual')->update([
            'sunat_worker_slots' => 1,
            'sunat_dedicated_queue' => false,
        ]);
        DB::table('plans')->where('slug', 'pro-mensual')->update([
            'sunat_worker_slots' => 2,
            'sunat_dedicated_queue' => false,
        ]);
        DB::table('plans')->where('slug', 'enterprise-anual')->update([
            'sunat_worker_slots' => 8,
            'sunat_dedicated_queue' => true,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('sunat_document_locks');
        Schema::dropIfExists('sunat_processing_slots');

        Schema::table('plans', function (Blueprint $table): void {
            $table->dropColumn(['description', 'sunat_worker_slots', 'sunat_dedicated_queue']);
        });
    }
};
