<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

final class SiteNav extends Model
{
    use LogsActivity;

    protected $fillable = [
        'site_id',
        'location',
        'config',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SiteNavItem::class, 'nav_id')->orderBy('sort_order');
    }

    public function rootItems(): HasMany
    {
        return $this->hasMany(SiteNavItem::class, 'nav_id')
            ->whereNull('parent_id')
            ->orderBy('sort_order');
    }

    public function scopeHeader(Builder $query): Builder
    {
        return $query->where('location', 'header');
    }

    public function scopeFooter(Builder $query): Builder
    {
        return $query->where('location', 'footer');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('site_navs')
            ->logOnly([
                'location',
                'config',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "SiteNav {$eventName}");
    }
}
