<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class Payment extends Model
{
    protected $fillable = [
        'subscription_id',
        'amount',
        'currency',
        'method',
        'status',
        'transaction_id',
        'provider_event_id',
        'paid_at',
        'provider_data',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'provider_data' => 'array',
    ];

    public function getConnectionName(): string
    {
        $connection = config('tenancy.database.central_connection');

        return is_string($connection) ? $connection : 'mysql';
    }

    public function subscription(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
