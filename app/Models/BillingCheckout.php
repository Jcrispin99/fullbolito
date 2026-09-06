<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class BillingCheckout extends Model
{
    protected $fillable = [
        'tenant_id',
        'plan_id',
        'replaces_subscription_id',
        'provider',
        'external_reference',
        'provider_id',
        'status',
        'amount',
        'currency',
        'checkout_url',
        'expires_at',
        'completed_at',
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

    public function plan(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function replacedSubscription(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'replaces_subscription_id');
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'expires_at' => 'datetime',
            'completed_at' => 'datetime',
            'provider_data' => 'array',
        ];
    }
}
