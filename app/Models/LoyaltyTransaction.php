<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

final class LoyaltyTransaction extends Model
{
    protected $fillable = [
        'loyalty_program_id',
        'loyalty_card_id',
        'partner_id',
        'type',
        'points',
        'balance',
        'description',
        'source_type',
        'source_id',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'points' => 'decimal:2',
            'balance' => 'decimal:2',
            'expires_at' => 'datetime',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(LoyaltyProgram::class, 'loyalty_program_id');
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(LoyaltyCard::class, 'loyalty_card_id');
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeForPartner(Builder $query, int $partnerId): Builder
    {
        return $query->where('partner_id', $partnerId);
    }

    public function scopeEarns(Builder $query): Builder
    {
        return $query->where('type', 'earn');
    }

    public function scopeRedemptions(Builder $query): Builder
    {
        return $query->where('type', 'redeem');
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('type', 'expire');
    }

    public function scopeLatestBalance(Builder $query): Builder
    {
        return $query->orderBy('created_at', 'desc');
    }
}
