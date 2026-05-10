<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->string('slug');
            $table->enum('type', ['static', 'blog', 'product_list', 'product_detail'])->default('static');
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->boolean('is_homepage')->default(false);
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->foreignId('og_image_asset_id')->nullable()->constrained('site_assets')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'slug']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_pages');
    }
};
