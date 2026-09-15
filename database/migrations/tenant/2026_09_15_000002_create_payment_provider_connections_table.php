<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_provider_connections', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 50);
            $table->string('environment', 20)->default('test');
            $table->text('access_token');
            $table->text('public_key')->nullable();
            $table->text('webhook_secret')->nullable();
            $table->string('external_account_id')->nullable();
            $table->string('account_nickname')->nullable();
            $table->string('country_id', 10)->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamp('validated_at')->nullable();
            $table->text('last_validation_error')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(
                ['provider', 'environment'],
                'payment_provider_connections_provider_env_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_provider_connections');
    }
};
