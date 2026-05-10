<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

final class SiteTheme extends Model
{
    use LogsActivity;

    protected $fillable = [
        'site_id',
        'name',
        'is_active',
        'colors',
        'typography',
        'spacing',
        'borders',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'colors' => 'array',
            'typography' => 'array',
            'spacing' => 'array',
            'borders' => 'array',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('site_themes')
            ->logOnly([
                'name',
                'is_active',
                'colors',
                'typography',
                'spacing',
                'borders',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "SiteTheme {$eventName}");
    }
}
