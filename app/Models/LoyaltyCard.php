<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

final class LoyaltyCard extends Model
{
    use LogsActivity;

    protected $fillable = [
        'loyalty_program_id',
        'partner_id',
        'code',
        'points',
        'expiration_date',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'points' => 'decimal:2',
            'expiration_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(LoyaltyProgram::class, 'loyalty_program_id');
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(LoyaltyTransaction::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForPartner(Builder $query, int $partnerId): Builder
    {
        return $query->where('partner_id', $partnerId);
    }

    public function scopeByCode(Builder $query, string $code): Builder
    {
        return $query->where('code', $code);
    }

    public function isExpired(): bool
    {
        return $this->expiration_date && $this->expiration_date->isPast();
    }

    public function isUsable(): bool
    {
        return $this->is_active && ! $this->isExpired() && (float) $this->points > 0;
    }

    public static function generateCode(): string
    {
        return '044' . Str::upper(Str::random(9));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('loyalty_cards')
            ->logOnly(['points', 'is_active', 'expiration_date'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "LoyaltyCard {$eventName}");
    }
}
