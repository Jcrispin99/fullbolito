<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_navs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->onDelete('cascade');
            $table->enum('location', ['header', 'footer']);
            $table->json('config');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_navs');
    }
};
