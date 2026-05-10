<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo INEI (departamento / provincia / distrito) usado para emitir
 * comprobantes y guías SUNAT. Vive en la base CENTRAL — es data pública,
 * idéntica para todos los tenants, así que no se replica.
 *
 * Convenio de IDs:
 *   - department_id  : 2 dígitos    ('15' = Lima)
 *   - province_id    : 4 dígitos    ('1501' = Lima/Lima)  → concat(dpto, prov-2dig)
 *   - district_id    : 6 dígitos    ('150101' = Lima/Lima/Lima)  → ubigeo SUNAT
 *
 * Los tenants almacenan únicamente el ubigeo de 6 dígitos (e.g.
 * `warehouses.ubigeo`). El catálogo se consulta por API/lookup central.
 *
 * Collation utf8mb4 explícito porque distritos como
 * "Andrés Avelino Cáceres Dorregaray" tienen tildes — sin esto, falla en
 * DBs antiguas configuradas con utf8 (3 bytes).
 */
return new class extends Migration
{
    public function up(): void
    {
        $isMysql = DB::connection()->getDriverName() === 'mysql';

        $applyCollation = function (ColumnDefinition $col) use ($isMysql): ColumnDefinition {
            return $isMysql
                ? $col->charset('utf8mb4')->collation('utf8mb4_unicode_ci')
                : $col;
        };

        Schema::create('ubigeo_departments', function (Blueprint $table) use ($applyCollation) {
            $table->string('id', 2)->primary();
            $applyCollation($table->string('name'));
        });

        Schema::create('ubigeo_provinces', function (Blueprint $table) use ($applyCollation) {
            $table->string('id', 4)->primary();
            $applyCollation($table->string('name'));
            $table->string('department_id', 2);

            $table->foreign('department_id')
                ->references('id')
                ->on('ubigeo_departments')
                ->onDelete('cascade');

            $table->index(['department_id']);
        });

        Schema::create('ubigeo_districts', function (Blueprint $table) use ($applyCollation) {
            $table->string('id', 6)->primary();
            $applyCollation($table->string('name'));
            $table->string('province_id', 4);
            $table->string('department_id', 2);

            $table->foreign('province_id')
                ->references('id')
                ->on('ubigeo_provinces')
                ->onDelete('cascade');

            $table->foreign('department_id')
                ->references('id')
                ->on('ubigeo_departments')
                ->onDelete('cascade');

            $table->index(['province_id']);
            $table->index(['department_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ubigeo_districts');
        Schema::dropIfExists('ubigeo_provinces');
        Schema::dropIfExists('ubigeo_departments');
    }
};
