<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

final class SiteAsset extends Model
{
    use LogsActivity;

    protected $fillable = [
        'site_id',
        'original_name',
        'path',
        'mime_type',
        'size_bytes',
        'width',
        'height',
        'dominant_color',
        'alt_text',
        'variants',
        'folder',
    ];

    protected function casts(): array
    {
        return [
            'variants' => 'array',
            'size_bytes' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function scopeInFolder(Builder $query, string $folder): Builder
    {
        return $query->where('folder', $folder);
    }

    public function scopeImages(Builder $query): Builder
    {
        return $query->where('mime_type', 'like', 'image/%');
    }

    public function scopeVideos(Builder $query): Builder
    {
        return $query->where('mime_type', 'like', 'video/%');
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    public function isVideo(): bool
    {
        return str_starts_with($this->mime_type, 'video/');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('site_assets')
            ->logOnly([
                'original_name',
                'path',
                'mime_type',
                'alt_text',
                'folder',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "SiteAsset {$eventName}");
    }
}
