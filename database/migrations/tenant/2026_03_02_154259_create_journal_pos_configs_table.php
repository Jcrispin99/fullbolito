<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('journal_pos_configs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('pos_config_id')->constrained('pos_configs')->cascadeOnDelete();
            $table->foreignId('journal_id')->constrained('journals')->cascadeOnDelete();
            $table->string('document_type');
            $table->boolean('is_default')->default(false);

            $table->timestamps();

            $table->unique(['pos_config_id', 'journal_id', 'document_type'], 'journal_pos_configs_unique');
            $table->index(['pos_config_id', 'document_type'], 'journal_pos_configs_pos_config_document_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journal_pos_configs');
    }
};
