<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PosSessionPayment extends Model
{
    protected $fillable = [
        'pos_session_id',
        'sale_id',
        'payment_method_id',
        'amount',
        'reference_sale_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function posSession(): BelongsTo
    {
        return $this->belongsTo(PosSession::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function referenceSale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'reference_sale_id');
    }
}
