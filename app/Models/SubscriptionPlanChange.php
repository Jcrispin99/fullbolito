<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A provider-neutral audit record for an upgrade or a scheduled downgrade.
 * It always lives in the central database, even when created from a tenant URL.
 *
 * @property int $id
 * @property string $tenant_id
 * @property int $subscription_id
 * @property int $from_plan_id
 * @property int $to_plan_id
 * @property string $kind
 * @property string $status
 * @property string $external_reference
 * @property string $provider
 * @property string|null $provider_preference_id
 * @property string|null $provider_payment_id
 * @property string $current_recurring_amount
 * @property string $target_recurring_amount
 * @property string $proration_amount
 * @property string $currency
 * @property string|null $checkout_url
 * @property \Illuminate\Support\Carbon $effective_at
 * @property \Illuminate\Support\Carbon|null $billing_cycle_anchor
 * @property \Illuminate\Support\Carbon|null $paid_at
 * @property \Illuminate\Support\Carbon|null $applied_at
 * @property \Illuminate\Support\Carbon|null $last_attempt_at
 * @property int $attempts
 * @property string|null $error
 * @property array<string, mixed>|null $provider_data
 * @property Subscription|null $subscription
 * @property Plan|null $fromPlan
 * @property Plan|null $toPlan
 */
final class SubscriptionPlanChange extends Model
{
    protected $fillable = [
        'tenant_id',
        'subscription_id',
        'from_plan_id',
        'to_plan_id',
        'kind',
        'status',
        'external_reference',
        'provider',
        'provider_preference_id',
        'provider_payment_id',
        'current_recurring_amount',
        'target_recurring_amount',
        'proration_amount',
        'currency',
        'checkout_url',
        'effective_at',
        'billing_cycle_anchor',
        'paid_at',
        'applied_at',
        'last_attempt_at',
        'attempts',
        'error',
        'provider_data',
    ];

    public function getConnectionName(): string
    {
        $connection = config('tenancy.database.central_connection');

        return is_string($connection) ? $connection : 'mysql';
    }

    public function tenant(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function subscription(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function fromPlan(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Plan::class, 'from_plan_id');
    }

    public function toPlan(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Plan::class, 'to_plan_id');
    }

    protected function casts(): array
    {
        return [
            'current_recurring_amount' => 'decimal:2',
            'target_recurring_amount' => 'decimal:2',
            'proration_amount' => 'decimal:2',
            'effective_at' => 'datetime',
            'billing_cycle_anchor' => 'datetime',
            'paid_at' => 'datetime',
            'applied_at' => 'datetime',
            'last_attempt_at' => 'datetime',
            'attempts' => 'integer',
            'provider_data' => 'array',
        ];
    }
}
