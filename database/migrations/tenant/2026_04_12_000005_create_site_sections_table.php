<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('page_id')->nullable()->constrained('site_pages')->onDelete('cascade');
            $table->foreignId('parent_id')->nullable()->constrained('site_sections')->onDelete('cascade');
            $table->unsignedSmallInteger('column_index')->default(0);
            $table->string('block_type_key');
            $table->integer('sort_order')->default(0);

            // Canvas positioning (para elementos hijos dentro de una sección)
            $table->float('position_x')->default(0);
            $table->float('position_y')->default(0);
            $table->float('element_width')->nullable();
            $table->float('element_height')->nullable();
            $table->float('rotation')->default(0);
            $table->enum('position_mode', ['flow', 'absolute'])->default('flow');

            $table->json('layout');
            $table->json('content');
            $table->json('style_overrides')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->boolean('is_global')->default(false);
            $table->string('global_name')->nullable();
            $table->timestamps();

            $table->index('block_type_key');
            $table->index(['site_id', 'is_global']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_sections');
    }
};
