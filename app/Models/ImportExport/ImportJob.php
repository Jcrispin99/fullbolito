<?php

declare(strict_types=1);

namespace App\Models\ImportExport;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

final class ImportJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'resource',
        'template_id',
        'user_id',
        'original_filename',
        'file_path',
        'file_size',
        'format',
        'delimiter',
        'encoding',
        'mapping',
        'mode',
        'unique_key',
        'options',
        'status',
        'total_rows',
        'processed_rows',
        'imported_rows',
        'failed_rows',
        'errors_file_path',
        'error_summary',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'mapping' => 'array',
        'options' => 'array',
        'error_summary' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (ImportJob $job): void {
            if (empty($job->uuid)) {
                $job->uuid = (string) Str::uuid();
            }
        });
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ImportTemplate::class, 'template_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
