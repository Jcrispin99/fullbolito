<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class SiteTemplate extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'thumbnail_url',
        'category',
        'site_snapshot',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'site_snapshot' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
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
}
