<?php

declare(strict_types=1);

namespace App\ImportExport\Schemas;

use App\ImportExport\Support\FieldDefinition;
use App\Models\ProductTemplate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class ProductTemplateSchema extends AbstractResourceSchema
{
    public function key(): string
    {
        return 'product';
    }

    public function label(): string
    {
        return 'Productos';
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
            new FieldDefinition('sku', 'SKU', 'string', importable: false, help: 'Tomado de la variante principal'),
            new FieldDefinition('barcode', 'Código de barras', 'string', importable: false, help: 'Tomado de la variante principal'),
            new FieldDefinition('price', 'Precio', 'decimal', importable: false),
            new FieldDefinition('category_id', 'ID categoría', 'integer', importable: false),
            new FieldDefinition('category.name', 'Categoría', 'relation', importable: false, relationResource: 'category'),
            new FieldDefinition('category.full_name', 'Ruta de categoría', 'relation', importable: false, relationResource: 'category'),
            new FieldDefinition('uom_id', 'ID unidad', 'integer', importable: false),
            new FieldDefinition('uom.name', 'Unidad', 'relation', importable: false, relationResource: 'unit_of_measure'),
            new FieldDefinition('uom.symbol', 'Símbolo unidad', 'relation', importable: false, relationResource: 'unit_of_measure'),
            new FieldDefinition('is_service', 'Es servicio', 'boolean', importable: false),
            new FieldDefinition('is_pos_visible', 'Visible en POS', 'boolean', importable: false),
            new FieldDefinition('tracks_inventory', 'Controla inventario', 'boolean', importable: false),
            new FieldDefinition('tracked_by_lot', 'Por lote', 'boolean', importable: false),
            new FieldDefinition('expiration_alert_days', 'Días alerta vencimiento', 'integer', importable: false),
            new FieldDefinition('expiration_block_days', 'Días bloqueo vencimiento', 'integer', importable: false),
            new FieldDefinition('total_stock', 'Stock total', 'decimal', importable: false, help: 'Suma de stock de todas las variantes'),
            new FieldDefinition('variants_count', 'Cantidad de variantes', 'integer', importable: false),
            new FieldDefinition('productProducts.*.sku', 'SKUs variantes', 'string', importable: false, help: 'Lista de SKUs de todas las variantes'),
            new FieldDefinition('productProducts.*.barcode', 'Códigos de barras', 'string', importable: false),
            new FieldDefinition('productProducts.*.attribute_string', 'Atributos por variante', 'string', importable: false, help: 'Una entrada por variante con sus atributos concatenados'),
            new FieldDefinition('is_active', 'Activo', 'boolean', importable: false),
            new FieldDefinition('created_at', 'Creado en', 'datetime', importable: false),
            new FieldDefinition('updated_at', 'Actualizado en', 'datetime', importable: false),
        ];
    }

    public function query(): Builder
    {
        return ProductTemplate::query()
            ->with([
                'category',
                'uom',
                'productProducts.attributeValues.attribute',
            ]);
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
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('productProducts', function (Builder $v) use ($search): void {
                        $v->where('sku', 'like', "%{$search}%")
                            ->orWhere('barcode', 'like', "%{$search}%");
                    });
            });
        }

        if (! empty($filters['category_id'])) {
            $query->where('category_id', (int) $filters['category_id']);
        }

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        if (array_key_exists('is_pos_visible', $filters) && $filters['is_pos_visible'] !== null) {
            $query->where('is_pos_visible', (bool) $filters['is_pos_visible']);
        }

        if (array_key_exists('is_service', $filters) && $filters['is_service'] !== null) {
            $query->where('is_service', (bool) $filters['is_service']);
        }

        if (! empty($filters['ids']) && is_array($filters['ids'])) {
            $query->whereIn('id', $filters['ids']);
        }

        return $query;
    }

    /**
     * Compute virtual columns (total_stock, variants_count) without N+1 queries
     * by leaning on the eager-loaded productProducts collection.
     */
    public function exportValue(Model $model, string $columnKey): mixed
    {
        if ($columnKey === 'total_stock') {
            $total = 0.0;
            foreach ($model->productProducts as $variant) {
                $total += (float) ($variant->stock ?? 0);
            }
            return $this->normalizeScalar($total);
        }

        if ($columnKey === 'variants_count') {
            return (int) $model->productProducts->count();
        }

        return parent::exportValue($model, $columnKey);
    }
}
