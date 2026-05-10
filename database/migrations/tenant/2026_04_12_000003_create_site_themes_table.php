<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_themes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->json('colors');
            $table->json('typography');
            $table->json('spacing');
            $table->json('borders');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_themes');
    }
};
