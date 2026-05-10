<?php

declare(strict_types=1);

namespace App\ImportExport\Schemas;

use App\ImportExport\Support\FieldDefinition;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;

final class WarehouseSchema extends AbstractResourceSchema
{
    public function key(): string
    {
        return 'warehouse';
    }

    public function label(): string
    {
        return 'Almacenes';
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
            new FieldDefinition('location', 'Ubicación', 'string', importable: false),
            new FieldDefinition('ubigeo', 'Ubigeo', 'string', importable: false),
            new FieldDefinition('address_line', 'Dirección', 'string', importable: false),
            new FieldDefinition('establishment_code', 'Código de establecimiento', 'string', importable: false),
            new FieldDefinition('company_id', 'ID empresa', 'integer', importable: false),
            new FieldDefinition('company.business_name', 'Empresa', 'relation', importable: false, relationResource: 'company'),
            new FieldDefinition('inventories_count', 'Entradas de inventario', 'integer', importable: false),
            new FieldDefinition('pos_configs_count', 'Configuraciones POS', 'integer', importable: false),
            new FieldDefinition('is_active', 'Activo', 'boolean', importable: false),
            new FieldDefinition('created_at', 'Creado en', 'datetime', importable: false),
            new FieldDefinition('updated_at', 'Actualizado en', 'datetime', importable: false),
        ];
    }

    public function query(): Builder
    {
        return Warehouse::query()
            ->companyFiltered()
            ->with('company')
            ->withCount(['inventories', 'posConfigs']);
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
                    ->orWhere('location', 'like', "%{$search}%")
                    ->orWhere('address_line', 'like', "%{$search}%");
            });
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
