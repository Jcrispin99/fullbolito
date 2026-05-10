<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_jobs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->string('resource', 60);

            $table->foreignId('template_id')
                ->nullable()
                ->constrained('import_export_templates')
                ->nullOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('original_filename');
            $table->string('file_path');
            $table->unsignedBigInteger('file_size')->nullable();

            $table->enum('format', ['csv', 'xlsx'])->default('csv');
            $table->string('delimiter', 2)->nullable();
            $table->string('encoding', 20)->nullable();

            $table->json('mapping')->nullable();

            $table->enum('mode', ['create', 'update', 'upsert'])->default('create');
            $table->string('unique_key', 60)->nullable();

            $table->json('options')->nullable();

            $table->enum('status', [
                'draft',
                'queued',
                'processing',
                'done',
                'partial',
                'failed',
                'cancelled',
            ])->default('draft');

            $table->unsignedInteger('total_rows')->nullable();
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('imported_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);

            $table->string('errors_file_path')->nullable();
            $table->json('error_summary')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index(['resource', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_jobs');
    }
};
