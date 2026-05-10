<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * sites.favicon_asset_id forma una dependencia circular con site_assets
 * (site_assets.site_id → sites). Se agrega después de que ambas tablas
 * existen para romper el ciclo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->foreignId('favicon_asset_id')
                ->nullable()
                ->after('status')
                ->constrained('site_assets')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropForeign(['favicon_asset_id']);
            $table->dropColumn('favicon_asset_id');
        });
    }
};
