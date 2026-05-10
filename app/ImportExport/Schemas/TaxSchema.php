<?php

declare(strict_types=1);

namespace App\ImportExport\Schemas;

use App\ImportExport\Support\FieldDefinition;
use App\Models\Tax;
use Illuminate\Database\Eloquent\Builder;

final class TaxSchema extends AbstractResourceSchema
{
    public function key(): string
    {
        return 'tax';
    }

    public function label(): string
    {
        return 'Impuestos';
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
            new FieldDefinition('description', 'Descripción', 'string', importable: false),
            new FieldDefinition('invoice_label', 'Etiqueta en factura', 'string', importable: false),
            new FieldDefinition('tax_type', 'Tipo', 'string', importable: false, help: 'IGV, ISC, RETENCION, etc.'),
            new FieldDefinition('affectation_type_code', 'Cód. afectación SUNAT', 'string', importable: false),
            new FieldDefinition('rate_percent', 'Tasa %', 'decimal', importable: false),
            new FieldDefinition('is_price_inclusive', 'Incluido en precio', 'boolean', importable: false),
            new FieldDefinition('is_active', 'Activo', 'boolean', importable: false),
            new FieldDefinition('is_default', 'Por defecto', 'boolean', importable: false),
            new FieldDefinition('created_at', 'Creado en', 'datetime', importable: false),
            new FieldDefinition('updated_at', 'Actualizado en', 'datetime', importable: false),
        ];
    }

    public function query(): Builder
    {
        return Tax::query();
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
                    ->orWhere('invoice_label', 'like', "%{$search}%")
                    ->orWhere('tax_type', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['tax_type'])) {
            $query->where('tax_type', $filters['tax_type']);
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
