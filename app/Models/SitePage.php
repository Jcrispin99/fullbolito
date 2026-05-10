<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

final class SitePage extends Model
{
    use LogsActivity;

    protected $fillable = [
        'site_id',
        'title',
        'slug',
        'type',
        'status',
        'is_homepage',
        'seo_title',
        'seo_description',
        'og_image_asset_id',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'is_homepage' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(SiteSection::class, 'page_id')->orderBy('sort_order');
    }

    public function ogImage(): BelongsTo
    {
        return $this->belongsTo(SiteAsset::class, 'og_image_asset_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(SitePageVersion::class, 'page_id');
    }

    public function formSubmissions(): HasMany
    {
        return $this->hasMany(SiteFormSubmission::class, 'page_id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', 'draft');
    }

    public function scopeArchived(Builder $query): Builder
    {
        return $query->where('status', 'archived');
    }

    public function scopeHomepage(Builder $query): Builder
    {
        return $query->where('is_homepage', true);
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('site_pages')
            ->logOnly([
                'title',
                'slug',
                'type',
                'status',
                'is_homepage',
                'seo_title',
                'seo_description',
                'published_at',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "SitePage {$eventName}");
    }
}
