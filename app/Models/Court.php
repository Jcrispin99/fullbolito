<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\FiltersByCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

final class Court extends Model
{
    use HasFactory, LogsActivity, FiltersByCompany;

    protected $fillable = [
        'name',
        'slug',
        'code',
        'description',
        'sport',
        'surface',
        'capacity',
        'slot_duration_minutes',
        'product_product_id',
        'company_id',
        'is_active',
    ];

    protected $casts = [
        'capacity' => 'integer',
        'slot_duration_minutes' => 'integer',
        'is_active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function productProduct(): BelongsTo
    {
        return $this->belongsTo(ProductProduct::class, 'product_product_id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(CourtSchedule::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'name',
                'sport',
                'surface',
                'capacity',
                'slot_duration_minutes',
                'product_product_id',
                'is_active',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
