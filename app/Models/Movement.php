<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\FiltersByCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Movement: una entrada (entry) o salida (exit) de stock en un almacén.
 *
 * Puede ser parte de una Transferencia (vía transfer_id) o un movimiento
 * suelto (entrada/salida manual: stock inicial, merma, donación, etc).
 *
 * Cada Movement tiene su propio ciclo de aprobación independiente:
 *   draft -> submitted -> posted    (camino feliz; impacta kardex)
 *                       -> rejected (vuelve a editar)
 *   posted -> cancelled              (escribe contra-asiento al kardex)
 *
 * @property int $id
 * @property string $type
 * @property string $serie
 * @property string $correlative
 * @property \Illuminate\Support\Carbon $date
 * @property string $status
 * @property int|null $transfer_id
 * @property int $warehouse_id
 * @property int $company_id
 * @property float $total
 */
final class Movement extends Model
{
    use FiltersByCompany;
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'movements';

    protected $fillable = [
        'type',
        'serie',
        'correlative',
        'date',
        'total',
        'observation',
        'reason',
        'warehouse_id',
        'company_id',
        'journal_id',
        'transfer_id',
        'status',
        'submitted_at',
        'posted_at',
        'rejected_at',
        'cancelled_at',
        'created_user_id',
        'submitted_user_id',
        'posted_user_id',
        'rejected_user_id',
        'cancelled_user_id',
        'rejection_reason',
    ];

    protected $casts = [
        'date' => 'datetime',
        'submitted_at' => 'datetime',
        'posted_at' => 'datetime',
        'rejected_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'total' => 'decimal:4',
    ];

    // Relaciones

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

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(Transfer::class);
    }

    public function createdUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_user_id');
    }

    public function submittedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_user_id');
    }

    public function postedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_user_id');
    }

    public function rejectedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_user_id');
    }

    public function cancelledUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_user_id');
    }

    public function productables(): MorphMany
    {
        return $this->morphMany(Productable::class, 'productable');
    }

    public function inventories(): MorphMany
    {
        return $this->morphMany(Inventory::class, 'inventoryable');
    }

    // Scopes

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeEntries(Builder $query): Builder
    {
        return $query->where('type', 'entry');
    }

    public function scopeExits(Builder $query): Builder
    {
        return $query->where('type', 'exit');
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', 'draft');
    }

    public function scopeSubmitted(Builder $query): Builder
    {
        return $query->where('status', 'submitted');
    }

    public function scopePosted(Builder $query): Builder
    {
        return $query->where('status', 'posted');
    }

    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('status', 'rejected');
    }

    public function scopeCancelled(Builder $query): Builder
    {
        return $query->where('status', 'cancelled');
    }

    /**
     * Movimientos sueltos (no parte de una transferencia).
     */
    public function scopeStandalone(Builder $query): Builder
    {
        return $query->whereNull('transfer_id');
    }

    /**
     * Movimientos pareados (parte de una transferencia).
     */
    public function scopePaired(Builder $query): Builder
    {
        return $query->whereNotNull('transfer_id');
    }

    // Helpers de tipo

    public function isEntry(): bool
    {
        return $this->type === 'entry';
    }

    public function isExit(): bool
    {
        return $this->type === 'exit';
    }

    // Helpers de estado

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isSubmitted(): bool
    {
        return $this->status === 'submitted';
    }

    public function isPosted(): bool
    {
        return $this->status === 'posted';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Indica si el movement es parte de una transferencia (tiene header).
     */
    public function isPartOfTransfer(): bool
    {
        return $this->transfer_id !== null;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'status',
                'type',
                'warehouse_id',
                'transfer_id',
                'submitted_at',
                'posted_at',
                'rejected_at',
                'cancelled_at',
                'rejection_reason',
                'total',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
