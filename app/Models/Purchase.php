<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Activitylog\LogOptions;
use App\Models\Concerns\FiltersByCompany;
use Spatie\Activitylog\Traits\LogsActivity;

final class Purchase extends Model
{
    use HasFactory, LogsActivity, FiltersByCompany;

    protected $fillable = [
        'serie',
        'correlative',
        'journal_id',
        'date',
        'partner_id',
        'warehouse_id',
        'company_id',
        'buyer_id',
        'total',
        'observation',
        'status',
        'payment_status',
        'vendor_bill_number',
        'vendor_bill_date',
    ];

    protected $casts = [
        'total' => 'decimal:2',
        'date' => 'datetime',
        'vendor_bill_date' => 'date',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function productables(): MorphMany
    {
        return $this->morphMany(Productable::class, 'productable');
    }

    public function inventories(): MorphMany
    {
        return $this->morphMany(Inventory::class, 'inventoryable');
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopePosted($query)
    {
        return $query->where('status', 'posted');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'payment_status', 'total', 'partner_id', 'warehouse_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
