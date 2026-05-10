<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->string('custom_domain')->nullable()->unique();
            $table->enum('domain_status', ['pending', 'verifying', 'active', 'failed'])->nullable();
            $table->timestamp('domain_verified_at')->nullable();
            $table->string('domain_verification_token')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sites');
    }
};
