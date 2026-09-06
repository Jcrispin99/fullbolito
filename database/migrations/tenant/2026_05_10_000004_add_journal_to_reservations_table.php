<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            // Quitamos el code aislado: ahora la numeración vive en journal+sequence
            // (mismo patrón que sales) y se expone como "{serie}-{correlative}"
            // mediante un accessor en el modelo.
            $table->dropUnique('reservations_code_unique');
            $table->dropColumn('code');
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->foreignId('journal_id')
                ->after('id')
                ->constrained('journals')
                ->onDelete('cascade');

            $table->string('serie')->after('journal_id');
            $table->string('correlative')->after('serie');

            $table->unique(
                ['company_id', 'serie', 'correlative'],
                'reservation_unique_company_serie_corr',
            );
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropUnique('reservation_unique_company_serie_corr');
            $table->dropForeign(['journal_id']);
            $table->dropColumn(['journal_id', 'serie', 'correlative']);
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->string('code')->unique()->after('id');
        });
    }
};
