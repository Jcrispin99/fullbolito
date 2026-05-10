<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class LotInventory extends Model
{
    protected $table = 'lot_inventories';

    protected $fillable = [
        'lot_id',
        'warehouse_id',
        'quantity_balance',
    ];

    protected $casts = [
        'quantity_balance' => 'decimal:4',
    ];

    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
