<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->onDelete('cascade');
            $table->string('original_name');
            $table->string('path');
            $table->string('mime_type');
            $table->integer('size_bytes');
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->string('dominant_color')->nullable();
            $table->string('alt_text')->nullable();
            $table->json('variants')->nullable();
            $table->string('folder')->default('general');
            $table->timestamps();

            $table->index(['site_id', 'folder']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_assets');
    }
};
