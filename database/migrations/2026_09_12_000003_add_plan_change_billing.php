<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->unsignedSmallInteger('billing_rank')->default(0)->after('duration_days')->index();
        });

        DB::table('plans')->where('slug', 'free-trial')->update(['billing_rank' => 0]);
        DB::table('plans')->where('slug', 'basico-mensual')->update(['billing_rank' => 10]);
        DB::table('plans')->where('slug', 'pro-mensual')->update(['billing_rank' => 20]);
        DB::table('plans')->where('slug', 'enterprise-anual')->update(['billing_rank' => 30]);
        DB::table('plans')->where('billing_rank', 0)->where('price', '>', 0)->where('sunat_worker_slots', '<=', 1)->update(['billing_rank' => 10]);
        DB::table('plans')->where('billing_rank', 0)->where('price', '>', 0)->where('sunat_worker_slots', 2)->update(['billing_rank' => 20]);
        DB::table('plans')->where('billing_rank', 0)->where('price', '>', 0)->where('sunat_worker_slots', 4)->update(['billing_rank' => 25]);
        DB::table('plans')->where('billing_rank', 0)->where('price', '>', 0)->where('sunat_worker_slots', '>=', 8)->update(['billing_rank' => 30]);

        Schema::create('subscription_plan_changes', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('subscription_id')->constrained('subscriptions')->cascadeOnDelete();
            $table->foreignId('from_plan_id')->constrained('plans');
            $table->foreignId('to_plan_id')->constrained('plans');
            $table->string('kind')->index();
            $table->string('status')->index();
            $table->uuid('external_reference')->unique();
            $table->string('provider')->default('mercadopago');
            $table->string('provider_preference_id')->nullable()->unique();
            $table->string('provider_payment_id')->nullable()->index();
            $table->decimal('current_recurring_amount', 10, 2);
            $table->decimal('target_recurring_amount', 10, 2);
            $table->decimal('proration_amount', 10, 2)->default(0);
            $table->string('currency', 3)->default('PEN');
            $table->text('checkout_url')->nullable();
            $table->timestamp('effective_at');
            $table->timestamp('billing_cycle_anchor')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('error')->nullable();
            $table->json('provider_data')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'status']);
            $table->index(['status', 'effective_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plan_changes');

        Schema::table('plans', function (Blueprint $table): void {
            $table->dropIndex(['billing_rank']);
            $table->dropColumn('billing_rank');
        });
    }
};
