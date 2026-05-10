<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movements', function (Blueprint $table) {
            $table->id();

            // Tipo de movimiento: entrada o salida del almacén.
            $table->enum('type', ['entry', 'exit']);

            // Identificación del documento
            $table->string('serie');
            $table->string('correlative');

            $table->timestamp('date')->useCurrent();

            // Total valorizado al costo
            $table->decimal('total', 12, 4)->default(0);

            // Texto libre del usuario
            $table->string('observation')->nullable();

            // Motivo del movimiento (ej: 'transfer_exit', 'transfer_entry',
            // 'merma', 'inicial', 'produccion', 'donacion', 'ajuste'...)
            $table->string('reason')->nullable();

            // Almacén impactado
            $table->foreignId('warehouse_id')
                ->constrained('warehouses')
                ->restrictOnDelete();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->foreignId('journal_id')
                ->nullable()
                ->constrained('journals')
                ->nullOnDelete();

            // Pareado con un Transfer (header) cuando es parte de una
            // transferencia entre almacenes. NULL para movimientos sueltos
            // (entradas / salidas manuales).
            $table->foreignId('transfer_id')
                ->nullable()
                ->constrained('transfers')
                ->cascadeOnDelete();

            // Workflow formal: cada movement tiene su propio ciclo
            //   draft -> submitted -> posted     (camino feliz)
            //                       -> rejected  (devuelto al solicitante)
            //   posted -> cancelled              (reversión vía contra-asiento)
            $table->enum('status', ['draft', 'submitted', 'posted', 'rejected', 'cancelled'])
                ->default('draft');

            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            // Auditoría de usuarios por transición
            $table->foreignId('created_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('submitted_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('posted_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('rejected_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('cancelled_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Motivo del rechazo (texto libre del aprobador)
            $table->string('rejection_reason')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Índices
            $table->index(['type', 'status']);
            $table->index(['warehouse_id', 'date']);
            $table->index(['company_id', 'date']);
            $table->index('transfer_id');
            $table->unique(
                ['company_id', 'serie', 'correlative'],
                'movement_unique_company_serie_corr'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movements');
    }
};
