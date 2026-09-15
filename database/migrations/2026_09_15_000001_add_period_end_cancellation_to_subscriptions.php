<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->boolean('cancel_at_period_end')->default(false);
            $table->timestamp('cancellation_requested_at')->nullable();
            $table->string('cancellation_requested_by')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->index(
                ['status', 'cancel_at_period_end', 'ends_at'],
                'subscriptions_cancellation_period_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropIndex('subscriptions_cancellation_period_index');
            $table->dropColumn([
                'cancel_at_period_end',
                'cancellation_requested_at',
                'cancellation_requested_by',
                'cancellation_reason',
            ]);
        });
    }
};
