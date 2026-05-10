<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SiteFormSubmission extends Model
{
    protected $fillable = [
        'site_id',
        'page_id',
        'section_id',
        'form_type',
        'data',
        'ip_address',
        'user_agent',
        'status',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'read_at' => 'datetime',
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

    public function section(): BelongsTo
    {
        return $this->belongsTo(SiteSection::class, 'section_id');
    }

    public function scopeNew(Builder $query): Builder
    {
        return $query->where('status', 'new');
    }

    public function scopeRead(Builder $query): Builder
    {
        return $query->where('status', 'read');
    }

    public function scopeArchived(Builder $query): Builder
    {
        return $query->where('status', 'archived');
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('form_type', $type);
    }

    public function markAsRead(): void
    {
        $this->update([
            'status' => 'read',
            'read_at' => now(),
        ]);
    }
}
