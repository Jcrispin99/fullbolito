<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'subscription_id',
        'amount',
        'currency',
        'method',
        'status',
        'transaction_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function subscription(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
