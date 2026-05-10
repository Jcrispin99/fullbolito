<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_page_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained('site_pages')->onDelete('cascade');
            $table->unsignedInteger('version_number');
            $table->string('title');
            $table->string('slug');
            $table->json('sections_snapshot');
            $table->json('seo_snapshot')->nullable();
            $table->string('reason')->default('publish');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->unique(['page_id', 'version_number']);
            $table->index('page_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_page_versions');
    }
};
