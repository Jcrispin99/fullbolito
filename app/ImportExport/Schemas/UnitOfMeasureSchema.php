<?php

declare(strict_types=1);

namespace App\ImportExport\Schemas;

use App\ImportExport\Support\FieldDefinition;
use App\Models\UnitOfMeasure;
use Illuminate\Database\Eloquent\Builder;

final class UnitOfMeasureSchema extends AbstractResourceSchema
{
    public function key(): string
    {
        return 'unit_of_measure';
    }

    public function label(): string
    {
        return 'Unidades de medida';
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
            new FieldDefinition('name', 'Nombre', 'string', required: true, importable: false),
            new FieldDefinition('symbol', 'Símbolo', 'string', importable: false),
            new FieldDefinition('family', 'Familia', 'string', importable: false, help: 'Peso, Volumen, Longitud, etc.'),
            new FieldDefinition('factor', 'Factor', 'decimal', importable: false, help: 'Factor de conversión a la unidad base'),
            new FieldDefinition('base_unit_id', 'ID unidad base', 'integer', importable: false),
            new FieldDefinition('baseUnit.name', 'Unidad base', 'relation', importable: false, relationResource: 'unit_of_measure'),
            new FieldDefinition('baseUnit.symbol', 'Símbolo base', 'relation', importable: false, relationResource: 'unit_of_measure'),
            new FieldDefinition('is_active', 'Activo', 'boolean', importable: false),
            new FieldDefinition('created_at', 'Creado en', 'datetime', importable: false),
            new FieldDefinition('updated_at', 'Actualizado en', 'datetime', importable: false),
        ];
    }

    public function query(): Builder
    {
        return UnitOfMeasure::query()->with('baseUnit');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function applyFilters(Builder $query, array $filters): Builder
    {
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('symbol', 'like', "%{$search}%")
                    ->orWhere('family', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['family'])) {
            $query->where('family', $filters['family']);
        }

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        if (! empty($filters['ids']) && is_array($filters['ids'])) {
            $query->whereIn('id', $filters['ids']);
        }

        return $query;
    }
}
