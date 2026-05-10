<?php

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
        // Nota: Ya existe una tabla 'subscriptions' creada por nosotros antes.
        // Vamos a modificarla o adaptarla.
        // Para evitar conflictos con Cashier, renombramos nuestra tabla anterior o usamos la de Cashier.
        // Dado que Cashier espera esta estructura, vamos a usarla pero apuntando a 'tenant_id'.

        // Si la tabla ya existe (por nuestra migración anterior), la modificamos.
        if (Schema::hasTable('subscriptions')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                // Columnas de Cashier
                $table->string('type')->after('tenant_id')->default('default');
                $table->string('stripe_id')->unique()->nullable()->after('type');
                $table->string('stripe_status')->nullable()->after('stripe_id');
                $table->string('stripe_price')->nullable()->after('stripe_status');
                $table->integer('quantity')->nullable()->after('stripe_price');
                // trial_ends_at y ends_at ya existen

                // Indices
                $table->index(['tenant_id', 'stripe_status']);
            });
        } else {
            Schema::create('subscriptions', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id'); // Usamos string para tenant_id (UUID/String)
                $table->string('type');
                $table->string('stripe_id')->unique();
                $table->string('stripe_status');
                $table->string('stripe_price')->nullable();
                $table->integer('quantity')->nullable();
                $table->timestamp('trial_ends_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'stripe_status']);
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
