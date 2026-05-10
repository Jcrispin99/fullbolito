<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_credentials', function (Blueprint $table) {
            $table->id();

            // Etiqueta libre — útil si se mantienen credenciales separadas
            // para sandbox/producción. La UI por defecto trabaja con un solo
            // registro pero el modelo no impone unicidad.
            $table->string('name')->default('Credenciales SUNAT');

            // ─── Credenciales SOL (clave SOL del contribuyente) ──────────
            $table->string('sol_user');
            // sol_pass se almacena cifrada (encrypted cast) — texto suficientemente largo.
            $table->text('sol_pass');
            // Ruta privada relativa al disk 'local' (storage/{tenant}/app/private/...).
            $table->string('cert_path');

            // ─── Credenciales API (proveedor / OAuth client) ─────────────
            $table->string('client_id')->nullable();
            // client_secret se almacena cifrada (encrypted cast).
            $table->text('client_secret')->nullable();

            // ─── Entorno ─────────────────────────────────────────────────
            // false = beta/sandbox SUNAT; true = producción (homologación pasada).
            $table->boolean('production')->default(false);

            // Activa/desactiva la credencial sin borrarla.
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_credentials');
    }
};
