<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();

            // Código público de la reserva (ej. RES-000123) — visible al cliente
            $table->string('code')->unique();

            $table->foreignId('court_id')->constrained('courts')->onDelete('cascade');
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');

            // Ventana de tiempo reservada
            $table->dateTime('start_at');
            $table->dateTime('end_at');

            // Ciclo de vida:
            // held       → slot bloqueado mientras el cliente completa el flujo
            // confirmed  → reserva confirmada, falta cobrar
            // paid       → pagada (genera Sale + Productable)
            // played     → la fecha pasó y se jugó
            // cancelled  → cancelada por cliente o admin
            // no_show    → no se presentó
            $table->enum('status', ['held', 'confirmed', 'paid', 'played', 'cancelled', 'no_show'])
                ->default('held');

            // Hasta cuándo dura el "hold" (para auto-liberar slots abandonados)
            $table->timestamp('held_until')->nullable();

            // Cliente registrado (opcional, para facturación SUNAT)
            $table->foreignId('partner_id')
                ->nullable()
                ->constrained('partners')
                ->nullOnDelete();

            // Datos del invitado (cuando reserva sin cuenta)
            $table->string('customer_name')->nullable();
            $table->string('customer_phone')->nullable();
            $table->string('customer_email')->nullable();

            // Precio total acordado (incluye impuestos). El detalle subtotal/IGV
            // se calcula al generar la Sale a partir del ProductProduct + Tax.
            $table->decimal('total', 10, 2)->default(0);

            $table->text('notes')->nullable();

            // Venta generada al cobrar (nullable hasta status=paid)
            $table->foreignId('sale_id')
                ->nullable()
                ->constrained('sales')
                ->nullOnDelete();

            // Quién la creó: null = reserva pública (web), seteado = admin/cajero
            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();

            $table->timestamps();

            // Consulta principal: disponibilidad de una cancha en un rango
            $table->index(['court_id', 'start_at'], 'reservations_court_start_idx');
            $table->index(['court_id', 'status', 'start_at'], 'reservations_court_status_idx');
            $table->index('status');
            $table->index('held_until');
            $table->index(['company_id', 'start_at'], 'reservations_company_start_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
