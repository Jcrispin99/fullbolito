<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\FiltersByCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Transfer: header / agrupador del par de Movements (exit + entry) que
 * componen una transferencia entre almacenes.
 *
 * El estado de la transferencia se DERIVA del par de movements; no se
 * almacena en esta tabla para evitar drift con la verdad (que vive en
 * los movements).
 *
 * Estados lógicos derivados:
 *   - draft           : ambos movements en draft
 *   - pending_exit    : exit submitted, entry no posted
 *   - in_transit      : exit posted, entry no posted
 *   - completed       : ambos posted
 *   - with_observation: exit posted, entry rejected
 *   - cancelled       : exit cancelled (no importa el entry)
 *
 * @property int $id
 * @property string $serie
 * @property string $correlative
 * @property \Illuminate\Support\Carbon $date
 * @property int $company_id
 * @property int|null $created_user_id
 * @property float $total
 * @property string|null $observation
 */
final class Transfer extends Model
{
    use FiltersByCompany;
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'transfers';

    protected $fillable = [
        'serie',
        'correlative',
        'date',
        'company_id',
        'created_user_id',
        'total',
        'observation',
        // GRE — Guía de Remisión Electrónica
        'gre_motive_code',
        'gre_modality',
        'gre_transfer_start_date',
        'gre_gross_weight',
        'gre_packages',
        'gre_vehicle_plate',
        'gre_driver_doc_type',
        'gre_driver_doc_number',
        'gre_driver_license',
        'gre_driver_name',
        'gre_status',
        'gre_ticket',
        'gre_response',
        'gre_sent_at',
        'gre_signed_xml_path',
        'gre_cdr_zip_path',
    ];

    protected $casts = [
        'date' => 'datetime',
        'total' => 'decimal:4',
        'gre_transfer_start_date' => 'date',
        'gre_gross_weight' => 'decimal:3',
        'gre_packages' => 'integer',
        'gre_response' => 'array',
        'gre_sent_at' => 'datetime',
    ];

    // Relaciones

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function createdUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_user_id');
    }

    /**
     * Todos los movements (exit + entry) que pertenecen al header.
     */
    public function movements(): HasMany
    {
        return $this->hasMany(Movement::class);
    }

    /**
     * El movement de salida (origen). Hay máximo 1 por transfer.
     */
    public function exitMovement(): HasOne
    {
        return $this->hasOne(Movement::class)->where('type', 'exit');
    }

    /**
     * El movement de entrada (destino). Hay máximo 1 por transfer.
     */
    public function entryMovement(): HasOne
    {
        return $this->hasOne(Movement::class)->where('type', 'entry');
    }

    // Scopes

    /**
     * Visibles si el header pertenece a alguna de las companies, o si los
     * almacenes (origen/destino) de sus movements están en alguna de ellas.
     *
     * @param  array<int, int>|null  $companyIds
     */
    public function scopeVisibleToCompanies(Builder $query, ?array $companyIds): Builder
    {
        if (empty($companyIds)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($companyIds) {
            $q->whereIn('transfers.company_id', $companyIds)
                ->orWhereHas(
                    'movements.warehouse',
                    fn (Builder $w) => $w->whereIn('warehouses.company_id', $companyIds)
                );
        });
    }

    // Helpers de estado derivado

    /**
     * Calcula el estado lógico del transfer combinando los estados de los
     * dos movements pareados.
     */
    public function derivedStatus(): string
    {
        $this->loadMissing(['exitMovement', 'entryMovement']);

        /** @var Movement|null $exit */
        $exit = $this->exitMovement;
        /** @var Movement|null $entry */
        $entry = $this->entryMovement;

        $exitStatus = $exit?->status;
        $entryStatus = $entry?->status;

        // Cancelado: si cualquiera de los dos está cancelled, el header lo está
        if ($exitStatus === 'cancelled' || $entryStatus === 'cancelled') {
            return 'cancelled';
        }

        // Ambos posted = completado
        if ($exitStatus === 'posted' && $entryStatus === 'posted') {
            return 'completed';
        }

        // Exit posted + entry rejected = recibida con observación
        if ($exitStatus === 'posted' && $entryStatus === 'rejected') {
            return 'with_observation';
        }

        // Exit posted, entry aún no posted => en tránsito
        if ($exitStatus === 'posted') {
            return 'in_transit';
        }

        // Exit submitted = esperando aprobación origen
        if ($exitStatus === 'submitted') {
            return 'pending_exit';
        }

        // Default: borrador
        return 'draft';
    }

    public function isDraft(): bool
    {
        return $this->derivedStatus() === 'draft';
    }

    public function isInTransit(): bool
    {
        return $this->derivedStatus() === 'in_transit';
    }

    public function isCompleted(): bool
    {
        return $this->derivedStatus() === 'completed';
    }

    public function isCancelled(): bool
    {
        return $this->derivedStatus() === 'cancelled';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'serie',
                'correlative',
                'company_id',
                'created_user_id',
                'total',
                'observation',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
