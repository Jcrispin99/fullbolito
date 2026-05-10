<?php

declare(strict_types=1);

namespace App\ImportExport\Schemas;

use App\ImportExport\Support\FieldDefinition;
use App\Models\PosConfig;
use Illuminate\Database\Eloquent\Builder;

final class PosConfigSchema extends AbstractResourceSchema
{
    public function key(): string
    {
        return 'pos_config';
    }

    public function label(): string
    {
        return 'Configuraciones POS';
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
            new FieldDefinition('company_id', 'ID empresa', 'integer', importable: false),
            new FieldDefinition('company.business_name', 'Empresa', 'relation', importable: false, relationResource: 'company'),
            new FieldDefinition('warehouse_id', 'ID almacén', 'integer', importable: false),
            new FieldDefinition('warehouse.name', 'Almacén', 'relation', importable: false, relationResource: 'warehouse'),
            new FieldDefinition('default_customer_id', 'ID cliente por defecto', 'integer', importable: false),
            new FieldDefinition('defaultCustomer.name', 'Cliente por defecto', 'relation', importable: false, relationResource: 'partner'),
            new FieldDefinition('tax_id', 'ID impuesto', 'integer', importable: false),
            new FieldDefinition('tax.name', 'Impuesto', 'relation', importable: false, relationResource: 'tax'),
            new FieldDefinition('apply_tax', 'Aplicar impuesto', 'boolean', importable: false),
            new FieldDefinition('prices_include_tax', 'Precios incluyen impuesto', 'boolean', importable: false),
            new FieldDefinition('default_lot_strategy', 'Estrategia de lotes', 'string', importable: false),
            new FieldDefinition('allow_expired_sale_with_override', 'Permite venta vencida (con override)', 'boolean', importable: false),
            new FieldDefinition('lot_scan_mode', 'Modo de escaneo de lote', 'string', importable: false),
            new FieldDefinition('auto_print_receipt', 'Imprimir recibo automáticamente', 'boolean', importable: false),
            new FieldDefinition('is_active', 'Activo', 'boolean', importable: false),
            new FieldDefinition('created_at', 'Creado en', 'datetime', importable: false),
            new FieldDefinition('updated_at', 'Actualizado en', 'datetime', importable: false),
        ];
    }

    public function query(): Builder
    {
        return PosConfig::query()
            ->companyFiltered()
            ->with(['company', 'warehouse', 'defaultCustomer', 'tax']);
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
