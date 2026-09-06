<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\FiltersByCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

final class Reservation extends Model
{
    use HasFactory, LogsActivity, FiltersByCompany;

    protected $fillable = [
        'journal_id',
        'serie',
        'correlative',
        'court_id',
        'company_id',
        'start_at',
        'end_at',
        'status',
        'held_until',
        'partner_id',
        'customer_name',
        'customer_phone',
        'customer_email',
        'total',
        'notes',
        'sale_id',
        'created_by_user_id',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'held_until' => 'datetime',
        'cancelled_at' => 'datetime',
        'total' => 'decimal:2',
    ];

    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    /**
     * Código público de la reserva (ej. RES-00000001) compuesto desde
     * serie+correlativo del journal asociado. Mantiene retrocompatibilidad
     * con el campo `code` que existía antes de migrar a journal+sequence.
     */
    public function getCodeAttribute(): string
    {
        return trim(($this->serie ?? '') . '-' . ($this->correlative ?? ''), '-');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    // Estados que ocupan el slot (bloquean disponibilidad)
    public function scopeBlocking(Builder $query): Builder
    {
        return $query->whereIn('status', ['held', 'confirmed', 'paid', 'played']);
    }

    /**
     * Reservas que bloquean disponibilidad AHORA — incluye holds vigentes
     * y excluye holds expirados. Usado por el cálculo de disponibilidad pública.
     */
    public function scopeCurrentlyBlocking(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->whereIn('status', ['confirmed', 'paid', 'played'])
                ->orWhere(function (Builder $q2): void {
                    $q2->where('status', 'held')
                        ->where('held_until', '>', now());
                });
        });
    }

    public function scopeActiveHold(Builder $query): Builder
    {
        return $query->where('status', 'held')
            ->where('held_until', '>', now());
    }

    public function scopeOverlapping(Builder $query, \DateTimeInterface $start, \DateTimeInterface $end): Builder
    {
        return $query->where('start_at', '<', $end)
            ->where('end_at', '>', $start);
    }

    public function isBlocking(): bool
    {
        if ($this->status === 'held') {
            return $this->held_until !== null && $this->held_until->isFuture();
        }

        return in_array($this->status, ['confirmed', 'paid', 'played'], true);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'journal_id',
                'serie',
                'correlative',
                'court_id',
                'start_at',
                'end_at',
                'status',
                'partner_id',
                'customer_name',
                'total',
                'sale_id',
                'cancelled_at',
                'cancellation_reason',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
