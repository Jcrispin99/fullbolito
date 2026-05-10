<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_form_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->onDelete('cascade');
            $table->foreignId('page_id')->nullable()->constrained('site_pages')->onDelete('set null');
            $table->foreignId('section_id')->nullable()->constrained('site_sections')->onDelete('set null');
            $table->string('form_type')->default('contact');
            $table->json('data');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->enum('status', ['new', 'read', 'archived'])->default('new');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'form_type']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_form_submissions');
    }
};
