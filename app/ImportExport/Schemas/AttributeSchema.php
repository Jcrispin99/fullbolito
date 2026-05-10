<?php

declare(strict_types=1);

namespace App\ImportExport\Schemas;

use App\ImportExport\Support\FieldDefinition;
use App\Models\Attribute;
use Illuminate\Database\Eloquent\Builder;

final class AttributeSchema extends AbstractResourceSchema
{
    public function key(): string
    {
        return 'attribute';
    }

    public function label(): string
    {
        return 'Atributos';
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
            new FieldDefinition('attribute_values_count', 'Cantidad de valores', 'integer', importable: false),
            new FieldDefinition('attributeValues.*.value', 'Valores', 'string', importable: false, help: 'Lista de valores definidos para este atributo'),
            new FieldDefinition('is_active', 'Activo', 'boolean', importable: false),
            new FieldDefinition('created_at', 'Creado en', 'datetime', importable: false),
            new FieldDefinition('updated_at', 'Actualizado en', 'datetime', importable: false),
        ];
    }

    public function query(): Builder
    {
        return Attribute::query()
            ->with('attributeValues')
            ->withCount('attributeValues');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function applyFilters(Builder $query, array $filters): Builder
    {
        if (! empty($filters['search'])) {
            $query->where('name', 'like', "%{$filters['search']}%");
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
