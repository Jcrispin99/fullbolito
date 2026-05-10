<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

final class SiteBlockCatalog extends Model
{
    use CentralConnection;
    use LogsActivity;

    protected $table = 'site_block_catalog';

    protected $fillable = [
        'key',
        'name',
        'description',
        'category',
        'icon',
        'feature_gate',
        'schema',
        'default_content',
        'default_layout',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'schema' => 'array',
            'default_content' => 'array',
            'default_layout' => 'array',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeByCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order');
    }

    public function isFeatureGated(): bool
    {
        return ! is_null($this->feature_gate);
    }

    public function getContentSchema(): array
    {
        return $this->schema['fields'] ?? [];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('site_block_catalog')
            ->logOnly([
                'key',
                'name',
                'category',
                'feature_gate',
                'is_active',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "SiteBlockCatalog {$eventName}");
    }
}
