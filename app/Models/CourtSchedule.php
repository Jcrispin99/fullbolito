<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

final class CourtSchedule extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'court_id',
        'day_of_week',
        'start_time',
        'end_time',
        'price',
        'slot_duration_minutes',
        'is_active',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'price' => 'decimal:2',
        'slot_duration_minutes' => 'integer',
        'is_active' => 'boolean',
    ];

    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'court_id',
                'day_of_week',
                'start_time',
                'end_time',
                'price',
                'slot_duration_minutes',
                'is_active',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
