<?php

declare(strict_types=1);

namespace App\ImportExport\Schemas;

use App\ImportExport\Support\FieldDefinition;
use App\Models\Transfer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class TransferSchema extends AbstractResourceSchema
{
    public function key(): string
    {
        return 'transfer';
    }

    public function label(): string
    {
        return 'Transferencias';
    }

    public function supportsImport(): bool
    {
        return false;
    }

    /**
     * @return array<int, FieldDefinition>
     */
    public function fields(): array
    {
        return [
            new FieldDefinition('id', 'ID', 'integer', importable: false),
            new FieldDefinition('serie', 'Serie', 'string', importable: false),
            new FieldDefinition('correlative', 'Correlativo', 'string', importable: false),
            new FieldDefinition('date', 'Fecha', 'datetime', importable: false),

            new FieldDefinition('exitMovement.warehouse.name', 'Almacén origen', 'relation', importable: false, relationResource: 'warehouse'),
            new FieldDefinition('entryMovement.warehouse.name', 'Almacén destino', 'relation', importable: false, relationResource: 'warehouse'),

            new FieldDefinition('exitMovement.status', 'Estado salida', 'string', importable: false),
            new FieldDefinition('entryMovement.status', 'Estado entrada', 'string', importable: false),
            new FieldDefinition('derived_status', 'Estado de la transferencia', 'string', importable: false, help: 'Calculado desde los dos movimientos'),

            new FieldDefinition('total', 'Total', 'decimal', importable: false),
            new FieldDefinition('observation', 'Observación', 'string', importable: false),

            new FieldDefinition('company_id', 'ID empresa', 'integer', importable: false),
            new FieldDefinition('company.business_name', 'Empresa', 'relation', importable: false, relationResource: 'company'),
            new FieldDefinition('createdUser.name', 'Creado por', 'relation', importable: false),

            // GRE - Guía de Remisión Electrónica
            new FieldDefinition('gre_motive_code', 'Cód. motivo GRE', 'string', importable: false),
            new FieldDefinition('gre_modality', 'Modalidad GRE', 'string', importable: false),
            new FieldDefinition('gre_transfer_start_date', 'Inicio traslado', 'date', importable: false),
            new FieldDefinition('gre_gross_weight', 'Peso bruto', 'decimal', importable: false),
            new FieldDefinition('gre_packages', 'Bultos', 'integer', importable: false),
            new FieldDefinition('gre_vehicle_plate', 'Placa vehículo', 'string', importable: false),
            new FieldDefinition('gre_driver_name', 'Conductor', 'string', importable: false),
            new FieldDefinition('gre_driver_doc_number', 'Doc. conductor', 'string', importable: false),
            new FieldDefinition('gre_status', 'Estado GRE', 'string', importable: false),
            new FieldDefinition('gre_sent_at', 'Enviado a SUNAT', 'datetime', importable: false),

            new FieldDefinition('movements_count', 'Movimientos', 'integer', importable: false),

            new FieldDefinition('created_at', 'Creado en', 'datetime', importable: false),
            new FieldDefinition('updated_at', 'Actualizado en', 'datetime', importable: false),
        ];
    }

    public function query(): Builder
    {
        return Transfer::query()
            ->visibleToCompanies(request()->get('_company_ids'))
            ->with([
                'company',
                'createdUser',
                'exitMovement.warehouse',
                'entryMovement.warehouse',
            ])
            ->withCount('movements');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function applyFilters(Builder $query, array $filters): Builder
    {
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $q) use ($search): void {
                $q->where('serie', 'like', "%{$search}%")
                    ->orWhere('correlative', 'like', "%{$search}%")
                    ->orWhere('observation', 'like', "%{$search}%")
                    ->orWhere('gre_vehicle_plate', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('date', '<=', $filters['date_to']);
        }

        if (! empty($filters['gre_status'])) {
            $query->where('gre_status', $filters['gre_status']);
        }

        if (! empty($filters['ids']) && is_array($filters['ids'])) {
            $query->whereIn('id', $filters['ids']);
        }

        return $query;
    }

    /**
     * Compute derived_status from the eager-loaded exit/entry movements.
     */
    public function exportValue(Model $model, string $columnKey): mixed
    {
        if ($columnKey === 'derived_status') {
            return $this->normalizeScalar($model->derivedStatus());
        }

        return parent::exportValue($model, $columnKey);
    }
}
