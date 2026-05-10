<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Plans now own their module list in the DB instead of config/saas.php.
        // The wildcard '*' is represented by `plans.includes_all_modules = true`
        // (see add_includes_all_modules_to_plans migration).
        Schema::create('plan_module', function (Blueprint $table) {
            $table->foreignId('plan_id')->constrained('plans')->cascadeOnDelete();
            $table->foreignId('module_id')->constrained('modules')->cascadeOnDelete();
            $table->primary(['plan_id', 'module_id']);
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->boolean('includes_all_modules')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('includes_all_modules');
        });

        Schema::dropIfExists('plan_module');
    }
};
