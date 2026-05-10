<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $fillable = [
        'tenant_id',
        'plan_id',
        'status',
        'starts_at',
        'ends_at',
        'trial_ends_at',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'trial_ends_at' => 'datetime',
    ];

    /**
     * Subscriptions live in the central database — pin the connection so
     * direct queries from inside a tenant context don't hit the tenant DB.
     */
    public function getConnectionName(): string
    {
        return config('tenancy.database.central_connection') ?? parent::getConnectionName();
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
