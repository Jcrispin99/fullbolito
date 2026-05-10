<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

final class SiteNavItem extends Model
{
    use LogsActivity;

    protected $fillable = [
        'nav_id',
        'parent_id',
        'label',
        'type',
        'target',
        'sort_order',
        'open_new_tab',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'open_new_tab' => 'boolean',
        ];
    }

    public function nav(): BelongsTo
    {
        return $this->belongsTo(SiteNav::class, 'nav_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order');
    }

    public function isRoot(): bool
    {
        return is_null($this->parent_id);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('site_nav_items')
            ->logOnly([
                'label',
                'type',
                'target',
                'sort_order',
                'open_new_tab',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "SiteNavItem {$eventName}");
    }
}
