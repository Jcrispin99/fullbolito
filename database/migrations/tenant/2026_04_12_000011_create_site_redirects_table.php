<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_redirects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->onDelete('cascade');
            $table->string('from_slug');
            $table->string('to_slug');
            $table->unsignedSmallInteger('type')->default(301);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['site_id', 'from_slug']);
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_redirects');
    }
};
