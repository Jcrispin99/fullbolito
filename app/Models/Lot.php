<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\FiltersByCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

final class Lot extends Model
{
    use FiltersByCompany, HasFactory, LogsActivity, SoftDeletes;

    protected $table = 'lots';

    protected $fillable = [
        'product_product_id',
        'company_id',
        'lot_number',
        'manufactured_at',
        'expires_at',
        'supplier_id',
        'purchase_id',
        'initial_quantity',
        'initial_cost',
        'status',
        'notes',
    ];

    protected $casts = [
        'manufactured_at' => 'date',
        'expires_at' => 'date',
        'initial_quantity' => 'decimal:4',
        'initial_cost' => 'decimal:4',
    ];

    public function productProduct(): BelongsTo
    {
        return $this->belongsTo(ProductProduct::class, 'product_product_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'supplier_id');
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function lotInventories(): HasMany
    {
        return $this->hasMany(LotInventory::class);
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    public function productables(): HasMany
    {
        return $this->hasMany(Productable::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeForProduct(Builder $query, int $productProductId): Builder
    {
        return $query->where('product_product_id', $productProductId);
    }

    public function scopeFefo(Builder $query): Builder
    {
        // NULL expires_at al final (lotes sin vencimiento)
        return $query->orderByRaw('expires_at IS NULL ASC')
            ->orderBy('expires_at', 'asc')
            ->orderBy('id', 'asc'); // desempate por FIFO (id = orden de ingreso)
    }

    public function scopeExpiring(Builder $query, int $days): Builder
    {
        return $query->whereNotNull('expires_at')
            ->whereDate('expires_at', '<=', now()->addDays($days))
            ->whereDate('expires_at', '>=', now());
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->whereNotNull('expires_at')
            ->whereDate('expires_at', '<', now());
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isSellable(): bool
    {
        return $this->status === 'active' && ! $this->isExpired();
    }

    public function totalStock(): float
    {
        return (float) $this->lotInventories()->sum('quantity_balance');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'product_product_id',
                'lot_number',
                'expires_at',
                'status',
                'initial_quantity',
                'initial_cost',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
