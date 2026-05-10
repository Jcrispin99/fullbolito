<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_block_catalog', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('category', ['headers', 'content', 'media', 'grids', 'interactive', 'commerce']);
            $table->string('icon')->nullable();
            $table->string('feature_gate')->nullable();
            $table->json('schema');
            $table->json('default_content');
            $table->json('default_layout');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_block_catalog');
    }
};
