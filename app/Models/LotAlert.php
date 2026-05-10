<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\FiltersByCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class LotAlert extends Model
{
    use FiltersByCompany;

    protected $table = 'lot_alerts';

    protected $fillable = [
        'lot_id',
        'product_product_id',
        'company_id',
        'alert_type',
        'days_until_expiry',
        'alert_date',
        'message',
        'status',
        'read_at',
    ];

    protected $casts = [
        'alert_date' => 'date',
        'read_at' => 'datetime',
        'days_until_expiry' => 'integer',
    ];

    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    public function productProduct(): BelongsTo
    {
        return $this->belongsTo(ProductProduct::class, 'product_product_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->where('status', 'unread');
    }

    public function markAsRead(): void
    {
        if ($this->status !== 'read') {
            $this->status = 'read';
            $this->read_at = now();
            $this->save();
        }
    }
}
