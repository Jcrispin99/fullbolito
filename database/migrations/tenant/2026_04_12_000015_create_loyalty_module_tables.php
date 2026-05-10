<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo de fidelización: programas, reglas, recompensas, tarjetas y transacciones.
 *
 * Un programa agrupa reglas (cómo se ganan puntos) y recompensas (cómo se canjean).
 * Las tarjetas conectan partner ↔ programa y guardan el saldo de puntos. Las
 * transacciones registran cada movimiento (earn/redeem/expire/adjust) con su origen
 * polimórfico (sale/purchase/manual).
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── loyalty_programs ─────────────────────────────────────────────
        Schema::create('loyalty_programs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('program_type', ['promotion', 'coupon', 'loyalty', 'buy_x_get_y', 'promo_code']);
            // Flags por canal (reemplazan al campo `module` original).
            $table->boolean('is_pos')->default(false);
            $table->boolean('is_web')->default(false);
            $table->boolean('is_sales')->default(false);
            $table->enum('applies_on', ['current', 'future', 'both'])->default('current');
            $table->enum('trigger', ['auto', 'with_code'])->default('auto');
            $table->string('point_name')->default('Puntos');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->integer('max_uses')->nullable();
            $table->integer('max_uses_per_customer')->nullable();
            $table->integer('current_uses')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('program_type');
            $table->index('is_pos');
            $table->index('is_web');
            $table->index('is_sales');
            $table->index('is_active');
        });

        // ── loyalty_rules ───────────────────────────────────────────────
        Schema::create('loyalty_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loyalty_program_id')->constrained()->onDelete('cascade');
            $table->decimal('reward_point_amount', 10, 2)->default(1);
            $table->enum('reward_point_mode', ['order', 'money', 'unit'])->default('order');
            $table->integer('minimum_qty')->default(0);
            $table->decimal('minimum_amount', 10, 2)->default(0);
            $table->string('code')->nullable();
            $table->json('conditions')->nullable();
            $table->timestamps();

            $table->unique('code');
        });

        // ── loyalty_rule pivots ─────────────────────────────────────────
        Schema::create('loyalty_rule_product_product', function (Blueprint $table) {
            $table->foreignId('loyalty_rule_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_product_id')->constrained()->onDelete('cascade');
            $table->primary(['loyalty_rule_id', 'product_product_id'], 'lr_pp_primary');
        });

        Schema::create('loyalty_rule_product_template', function (Blueprint $table) {
            $table->foreignId('loyalty_rule_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_template_id')->constrained()->onDelete('cascade');
            $table->primary(['loyalty_rule_id', 'product_template_id'], 'lr_pt_primary');
        });

        Schema::create('loyalty_rule_category', function (Blueprint $table) {
            $table->foreignId('loyalty_rule_id')->constrained()->onDelete('cascade');
            $table->foreignId('category_id')->constrained()->onDelete('cascade');
            $table->primary(['loyalty_rule_id', 'category_id']);
        });

        // ── loyalty_rewards ─────────────────────────────────────────────
        Schema::create('loyalty_rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loyalty_program_id')->constrained()->onDelete('cascade');
            $table->enum('reward_type', ['discount', 'product'])->default('discount');
            $table->decimal('required_points', 10, 2)->default(1);
            $table->string('description')->nullable();

            // Discount fields
            $table->decimal('discount', 10, 2)->nullable();
            $table->enum('discount_mode', ['percent', 'fixed_amount', 'per_point'])->nullable();
            $table->enum('discount_applicability', ['order', 'cheapest', 'specific'])->nullable();
            $table->decimal('discount_max_amount', 10, 2)->nullable();

            // Product reward fields
            $table->foreignId('reward_product_id')->nullable()
                ->constrained('product_products')->onDelete('set null');
            $table->integer('reward_product_qty')->default(1);

            $table->timestamps();
        });

        // ── loyalty_reward pivots ───────────────────────────────────────
        Schema::create('loyalty_reward_product_product', function (Blueprint $table) {
            $table->foreignId('loyalty_reward_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_product_id')->constrained()->onDelete('cascade');
            $table->primary(['loyalty_reward_id', 'product_product_id'], 'lrw_pp_primary');
        });

        Schema::create('loyalty_reward_category', function (Blueprint $table) {
            $table->foreignId('loyalty_reward_id')->constrained()->onDelete('cascade');
            $table->foreignId('category_id')->constrained()->onDelete('cascade');
            $table->primary(['loyalty_reward_id', 'category_id']);
        });

        // ── loyalty_cards ───────────────────────────────────────────────
        Schema::create('loyalty_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loyalty_program_id')->constrained()->onDelete('cascade');
            $table->foreignId('partner_id')->nullable()->constrained()->onDelete('set null');
            $table->string('code')->unique();
            $table->decimal('points', 12, 2)->default(0);
            $table->date('expiration_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['loyalty_program_id', 'partner_id']);
            $table->index('code');
        });

        // ── loyalty_transactions ────────────────────────────────────────
        Schema::create('loyalty_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loyalty_program_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('loyalty_card_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('partner_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['earn', 'redeem', 'expire', 'adjust']);
            $table->integer('points');
            $table->integer('balance');
            $table->string('description')->nullable();
            $table->nullableMorphs('source');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['partner_id', 'type']);
            $table->index(['loyalty_card_id']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_transactions');
        Schema::dropIfExists('loyalty_cards');
        Schema::dropIfExists('loyalty_reward_category');
        Schema::dropIfExists('loyalty_reward_product_product');
        Schema::dropIfExists('loyalty_rewards');
        Schema::dropIfExists('loyalty_rule_category');
        Schema::dropIfExists('loyalty_rule_product_template');
        Schema::dropIfExists('loyalty_rule_product_product');
        Schema::dropIfExists('loyalty_rules');
        Schema::dropIfExists('loyalty_programs');
    }
};
