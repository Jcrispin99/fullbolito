<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SitePageVersion extends Model
{
    protected $fillable = [
        'page_id',
        'version_number',
        'title',
        'slug',
        'sections_snapshot',
        'seo_snapshot',
        'reason',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'sections_snapshot' => 'array',
            'seo_snapshot' => 'array',
            'version_number' => 'integer',
        ];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(SitePage::class, 'page_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
