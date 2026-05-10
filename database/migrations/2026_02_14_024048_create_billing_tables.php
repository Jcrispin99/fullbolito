<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Planes (SaaS define estos planes)
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // "Básico", "Pro", "Enterprise"
            $table->string('slug')->unique(); // "basico-mensual"
            $table->decimal('price', 10, 2); // 10.00
            // Stripe Cashier mapping. Nullable: legacy / dev plans pueden vivir
            // sin Stripe — StripePlanService trata null como "DB-only".
            $table->string('stripe_price_id')->nullable();
            $table->string('stripe_product_id')->nullable();
            $table->integer('duration_days'); // 30 (mensual), 365 (anual)
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Suscripciones (Tenant -> Plan)
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id'); // Relación manual con tabla tenants (string ID)
            $table->foreignId('plan_id')->constrained('plans');

            $table->string('status')->default('active'); // active, expired, cancelled, trial
            $table->timestamp('starts_at')->useCurrent();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();

            $table->timestamps();

            // Índices para búsquedas rápidas
            $table->index(['tenant_id', 'status']);

            // Integridad referencial (asumiendo que tenant_id es string en tenants table)
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        // 3. Pagos (Registro histórico)
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained('subscriptions');

            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('method'); // "stripe", "transfer", "cash", "paypal"
            $table->string('status'); // "pending", "completed", "failed"
            $table->string('transaction_id')->nullable(); // ID externo de la pasarela

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plans');
    }
};
