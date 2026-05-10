<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

final class Site extends Model
{
    use LogsActivity;

    protected $fillable = [
        'name',
        'status',
        'custom_domain',
        'domain_status',
        'domain_verified_at',
        'domain_verification_token',
        'favicon_asset_id',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'domain_verified_at' => 'datetime',
        ];
    }

    public function theme(): HasOne
    {
        return $this->hasOne(SiteTheme::class);
    }

    public function activeTheme(): HasOne
    {
        return $this->hasOne(SiteTheme::class)->where('is_active', true);
    }

    public function pages(): HasMany
    {
        return $this->hasMany(SitePage::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(SiteAsset::class);
    }

    public function navs(): HasMany
    {
        return $this->hasMany(SiteNav::class);
    }

    public function formSubmissions(): HasMany
    {
        return $this->hasMany(SiteFormSubmission::class);
    }

    public function redirects(): HasMany
    {
        return $this->hasMany(SiteRedirect::class);
    }

    public function favicon(): BelongsTo
    {
        return $this->belongsTo(SiteAsset::class, 'favicon_asset_id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', 'draft');
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('sites')
            ->logOnly([
                'name',
                'status',
                'favicon_asset_id',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "Site {$eventName}");
    }
}
