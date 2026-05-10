<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla "header" slim de transferencias entre almacenes.
 *
 * El estado lógico (draft / pending_exit / in_transit / completed / cancelled)
 * se deriva en runtime del par de movements (exit + entry) que apuntan a este
 * header vía `transfer_id`. Los almacenes origen/destino y la auditoría de
 * envío/recepción viven en cada movement, no aquí.
 *
 * Incluye campos GRE (Guía de Remisión Electrónica) — fase 1: GRE-Remitente
 * (cód 09), motivo 04 (traslado interno mismo RUC), modalidad privada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfers', function (Blueprint $table) {
            $table->id();

            // Identificación del documento
            $table->string('serie');
            $table->string('correlative');

            $table->timestamp('date')->useCurrent();

            // Company operadora (la del almacén origen / usuario que crea).
            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            // Auditoría del creador del header
            $table->foreignId('created_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Total valorizado al costo (suma de líneas = qty * unit_cost)
            $table->decimal('total', 12, 4)->default(0);

            $table->string('observation')->nullable();

            // ─── GRE (Guía de Remisión Electrónica) ──────────────────────────
            // Datos del traslado SUNAT
            $table->string('gre_motive_code', 2)->nullable();
            $table->enum('gre_modality', ['public', 'private'])->nullable();
            $table->date('gre_transfer_start_date')->nullable();
            $table->decimal('gre_gross_weight', 10, 3)->nullable();
            $table->unsignedInteger('gre_packages')->nullable();

            // Transporte privado (modalidad 02) — fase 1
            $table->string('gre_vehicle_plate', 10)->nullable();
            $table->string('gre_driver_doc_type', 1)->nullable();
            $table->string('gre_driver_doc_number', 15)->nullable();
            $table->string('gre_driver_license', 20)->nullable();
            $table->string('gre_driver_name')->nullable();

            // Estado de envío SUNAT.
            // Estados: not_required | pending | processing | ticket_pending |
            //          accepted | rejected | error
            $table->string('gre_status')->default('not_required');
            $table->string('gre_ticket')->nullable();
            $table->json('gre_response')->nullable();
            $table->timestamp('gre_sent_at')->nullable();
            $table->string('gre_signed_xml_path')->nullable();
            $table->string('gre_cdr_zip_path')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('gre_status');
            $table->index('gre_ticket');
            $table->unique(
                ['company_id', 'serie', 'correlative'],
                'transfer_unique_company_serie_corr'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfers');
    }
};
