<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

final class SiteSection extends Model
{
    use LogsActivity;

    protected $fillable = [
        'site_id',
        'page_id',
        'parent_id',
        'column_index',
        'block_type_key',
        'sort_order',
        'position_x',
        'position_y',
        'element_width',
        'element_height',
        'rotation',
        'position_mode',
        'layout',
        'content',
        'style_overrides',
        'is_visible',
        'is_global',
        'global_name',
    ];

    protected function casts(): array
    {
        return [
            'layout' => 'array',
            'content' => 'array',
            'style_overrides' => 'array',
            'sort_order' => 'integer',
            'column_index' => 'integer',
            'position_x' => 'float',
            'position_y' => 'float',
            'element_width' => 'float',
            'element_height' => 'float',
            'rotation' => 'float',
            'is_visible' => 'boolean',
            'is_global' => 'boolean',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(SitePage::class, 'page_id');
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

    /**
     * Obtiene la definición del tipo de bloque desde el catálogo central.
     */
    public function blockCatalog(): ?SiteBlockCatalog
    {
        return SiteBlockCatalog::where('key', $this->block_type_key)->first();
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_visible', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order');
    }

    public function scopeGlobal(Builder $query): Builder
    {
        return $query->where('is_global', true);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('site_sections')
            ->logOnly([
                'block_type_key',
                'sort_order',
                'is_visible',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "SiteSection {$eventName}");
    }
}
