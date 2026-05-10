<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_nav_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nav_id')->constrained('site_navs')->onDelete('cascade');
            $table->foreignId('parent_id')->nullable()->constrained('site_nav_items')->nullOnDelete();
            $table->string('label');
            $table->enum('type', ['page', 'url', 'anchor']);
            $table->string('target');
            $table->integer('sort_order')->default(0);
            $table->boolean('open_new_tab')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_nav_items');
    }
};
