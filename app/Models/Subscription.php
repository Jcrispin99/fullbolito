<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $tenant_id
 * @property int $plan_id
 * @property string $status
 * @property string|null $provider
 * @property string|null $provider_id
 * @property string|null $provider_status
 * @property string|null $external_reference
 * @property \Illuminate\Support\Carbon|null $starts_at
 * @property \Illuminate\Support\Carbon|null $ends_at
 * @property \Illuminate\Support\Carbon|null $trial_ends_at
 * @property \Illuminate\Support\Carbon|null $next_billing_at
 * @property Plan|null $plan
 */
final class Subscription extends Model
{
    protected $fillable = [
        'tenant_id',
        'plan_id',
        'status',
        'starts_at',
        'ends_at',
        'trial_ends_at',
        'provider',
        'provider_id',
        'provider_status',
        'external_reference',
        'next_billing_at',
        'provider_data',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'next_billing_at' => 'datetime',
        'provider_data' => 'array',
    ];

    /**
     * Subscriptions live in the central database — pin the connection so
     * direct queries from inside a tenant context don't hit the tenant DB.
     */
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

    public function payments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function isValid(): bool
    {
        if (! in_array($this->status, ['active', 'trial'], true)) {
            return false;
        }

        if ($this->status === 'trial' && $this->trial_ends_at && $this->trial_ends_at->isPast()) {
            return false;
        }

        if ($this->status === 'active' && $this->ends_at && $this->ends_at->isPast()) {
            return false;
        }

        return true;
    }
}
