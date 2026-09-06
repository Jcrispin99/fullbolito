<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->string('billing_provider')->nullable()->index();
            $table->string('provider_customer_id')->nullable()->index();
            $table->string('payment_method_type')->nullable();
            $table->string('payment_method_last_four', 4)->nullable();
        });

        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->string('provider')->nullable()->index();
            $table->string('provider_id')->nullable()->unique();
            $table->string('provider_status')->nullable();
            $table->string('external_reference')->nullable()->unique();
            $table->timestamp('next_billing_at')->nullable();
            $table->json('provider_data')->nullable();
        });

        Schema::create('billing_checkouts', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('plan_id')->constrained('plans');
            $table->foreignId('replaces_subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
            $table->string('provider')->index();
            $table->uuid('external_reference')->unique();
            $table->string('provider_id')->nullable()->unique();
            $table->string('status')->default('creating')->index();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('PEN');
            $table->text('checkout_url')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('provider_data')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'status']);
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->string('provider_event_id')->nullable()->unique();
            $table->timestamp('paid_at')->nullable();
            $table->json('provider_data')->nullable();
        });

        Schema::create('payment_webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->string('provider');
            $table->string('event_id');
            $table->string('event_type');
            $table->string('action')->nullable();
            $table->string('resource_id')->nullable()->index();
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_events');

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropUnique(['provider_event_id']);
            $table->dropColumn(['provider_event_id', 'paid_at', 'provider_data']);
        });

        Schema::dropIfExists('billing_checkouts');

        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropUnique(['provider_id']);
            $table->dropUnique(['external_reference']);
            $table->dropIndex(['provider']);
            $table->dropColumn([
                'provider',
                'provider_id',
                'provider_status',
                'external_reference',
                'next_billing_at',
                'provider_data',
            ]);
        });

        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropIndex(['billing_provider']);
            $table->dropIndex(['provider_customer_id']);
            $table->dropColumn([
                'billing_provider',
                'provider_customer_id',
                'payment_method_type',
                'payment_method_last_four',
            ]);
        });
    }
};
