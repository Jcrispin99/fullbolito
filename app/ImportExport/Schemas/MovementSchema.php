<?php

declare(strict_types=1);

namespace App\ImportExport\Schemas;

use App\ImportExport\Support\FieldDefinition;
use App\Models\Movement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class MovementSchema extends AbstractResourceSchema
{
    public function key(): string
    {
        return 'movement';
    }

    public function label(): string
    {
        return 'Movimientos';
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
            new FieldDefinition('type', 'Tipo', 'enum', importable: false, enumValues: ['entry', 'exit']),
            new FieldDefinition('serie', 'Serie', 'string', importable: false),
            new FieldDefinition('correlative', 'Correlativo', 'string', importable: false),
            new FieldDefinition('date', 'Fecha', 'datetime', importable: false),

            new FieldDefinition('warehouse_id', 'ID almacén', 'integer', importable: false),
            new FieldDefinition('warehouse.name', 'Almacén', 'relation', importable: false, relationResource: 'warehouse'),

            new FieldDefinition('company_id', 'ID empresa', 'integer', importable: false),
            new FieldDefinition('company.business_name', 'Empresa', 'relation', importable: false, relationResource: 'company'),

            new FieldDefinition('journal_id', 'ID journal', 'integer', importable: false),
            new FieldDefinition('journal.name', 'Tipo de comprobante', 'relation', importable: false),

            new FieldDefinition('transfer_id', 'ID transferencia', 'integer', importable: false, help: 'Vacío si es movimiento suelto'),
            new FieldDefinition('transfer.serie', 'Serie transferencia', 'relation', importable: false),
            new FieldDefinition('transfer.correlative', 'Correlativo transferencia', 'relation', importable: false),

            new FieldDefinition('total', 'Total', 'decimal', importable: false),
            new FieldDefinition('reason', 'Motivo', 'string', importable: false),
            new FieldDefinition('observation', 'Observación', 'string', importable: false),

            new FieldDefinition('status', 'Estado', 'enum', importable: false, enumValues: ['draft', 'submitted', 'posted', 'rejected', 'cancelled']),
            new FieldDefinition('submitted_at', 'Enviado en', 'datetime', importable: false),
            new FieldDefinition('posted_at', 'Confirmado en', 'datetime', importable: false),
            new FieldDefinition('rejected_at', 'Rechazado en', 'datetime', importable: false),
            new FieldDefinition('cancelled_at', 'Cancelado en', 'datetime', importable: false),
            new FieldDefinition('rejection_reason', 'Razón de rechazo', 'string', importable: false),

            new FieldDefinition('createdUser.name', 'Creado por', 'relation', importable: false),
            new FieldDefinition('postedUser.name', 'Confirmado por', 'relation', importable: false),

            new FieldDefinition('lines_count', 'Cantidad de líneas', 'integer', importable: false),

            new FieldDefinition('created_at', 'Creado en', 'datetime', importable: false),
            new FieldDefinition('updated_at', 'Actualizado en', 'datetime', importable: false),
        ];
    }

    public function query(): Builder
    {
        return Movement::query()
            ->companyFiltered()
            ->with([
                'warehouse',
                'company',
                'journal',
                'transfer',
                'createdUser',
                'postedUser',
            ])
            ->withCount('productables');
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
                    ->orWhere('reason', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['type']) && $filters['type'] !== 'all') {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', (int) $filters['warehouse_id']);
        }

        if (array_key_exists('standalone', $filters) && $filters['standalone'] !== null) {
            if ($filters['standalone']) {
                $query->whereNull('transfer_id');
            } else {
                $query->whereNotNull('transfer_id');
            }
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('date', '<=', $filters['date_to']);
        }

        if (! empty($filters['ids']) && is_array($filters['ids'])) {
            $query->whereIn('id', $filters['ids']);
        }

        return $query;
    }

    /**
     * Map computed `lines_count` to the productables_count attribute populated by withCount.
     */
    public function exportValue(Model $model, string $columnKey): mixed
    {
        if ($columnKey === 'lines_count') {
            return $this->normalizeScalar($model->getAttribute('productables_count'));
        }

        return parent::exportValue($model, $columnKey);
    }
}
