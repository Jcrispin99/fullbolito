<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

final class LoyaltyProgram extends Model
{
    use LogsActivity;

    protected $fillable = [
        'name',
        'description',
        'program_type',
        'is_pos',
        'is_web',
        'is_sales',
        'applies_on',
        'trigger',
        'point_name',
        'starts_at',
        'ends_at',
        'max_uses',
        'max_uses_per_customer',
        'current_uses',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'max_uses' => 'integer',
            'max_uses_per_customer' => 'integer',
            'current_uses' => 'integer',
            'is_active' => 'boolean',
            'is_pos' => 'boolean',
            'is_web' => 'boolean',
            'is_sales' => 'boolean',
        ];
    }

    public function rules(): HasMany
    {
        return $this->hasMany(LoyaltyRule::class);
    }

    public function rewards(): HasMany
    {
        return $this->hasMany(LoyaltyReward::class);
    }

    public function cards(): HasMany
    {
        return $this->hasMany(LoyaltyCard::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(LoyaltyTransaction::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForModule(Builder $query, string $module): Builder
    {
        $column = match ($module) {
            'pos' => 'is_pos',
            'web' => 'is_web',
            'sales' => 'is_sales',
            default => null,
        };

        if ($column === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where($column, true);
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('program_type', $type);
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
        })->where(function (Builder $q) {
            $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
        });
    }

    public function isAvailable(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }

        if ($this->ends_at && $this->ends_at->isPast()) {
            return false;
        }

        if ($this->max_uses !== null && $this->current_uses >= $this->max_uses) {
            return false;
        }

        return true;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('loyalty_programs')
            ->logOnly([
                'name',
                'program_type',
                'is_pos',
                'is_web',
                'is_sales',
                'applies_on',
                'trigger',
                'starts_at',
                'ends_at',
                'is_active',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "LoyaltyProgram {$eventName}");
    }
}
