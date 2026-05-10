<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use App\Models\Concerns\FiltersByCompany;
use Spatie\Activitylog\Traits\LogsActivity;

final class Warehouse extends Model
{
    use HasFactory, LogsActivity, FiltersByCompany;

    protected $fillable = [
        'name',
        'location',
        'ubigeo',
        'address_line',
        'establishment_code',
        'is_active',
        'company_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    public function lotInventories(): HasMany
    {
        return $this->hasMany(LotInventory::class);
    }

    public function posConfigs(): HasMany
    {
        return $this->hasMany(PosConfig::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'location', 'is_active', 'company_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
