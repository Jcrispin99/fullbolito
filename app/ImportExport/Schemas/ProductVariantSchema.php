<?php

declare(strict_types=1);

namespace App\ImportExport\Schemas;

use App\ImportExport\Support\FieldDefinition;
use App\Models\Attribute;
use App\Models\ProductProduct;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class ProductVariantSchema extends AbstractResourceSchema
{
    public function key(): string
    {
        return 'product_variant';
    }

    public function label(): string
    {
        return 'Variantes de productos';
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
        $fields = [
            new FieldDefinition('id', 'ID', 'integer', importable: false),
            new FieldDefinition('sku', 'SKU', 'string', importable: false),
            new FieldDefinition('barcode', 'Código de barras', 'string', importable: false),
            new FieldDefinition('price', 'Precio', 'decimal', importable: false),
            new FieldDefinition('cost_price', 'Costo', 'decimal', importable: false),
            new FieldDefinition('is_principal', 'Es principal', 'boolean', importable: false),
            new FieldDefinition('product_template_id', 'ID producto', 'integer', importable: false),
            new FieldDefinition('product.name', 'Producto', 'relation', importable: false, relationResource: 'product'),
            new FieldDefinition('product.category.full_name', 'Categoría', 'relation', importable: false, relationResource: 'category'),
            new FieldDefinition('product.uom.name', 'Unidad', 'relation', importable: false, relationResource: 'unit_of_measure'),
            new FieldDefinition('product.uom.symbol', 'Símbolo unidad', 'relation', importable: false, relationResource: 'unit_of_measure'),
            new FieldDefinition('product.is_active', 'Producto activo', 'boolean', importable: false),
            new FieldDefinition('stock', 'Stock', 'decimal', importable: false, help: 'Stock total acumulado en todos los almacenes'),
            new FieldDefinition('attributeValues.*.value', 'Atributos (concatenados)', 'string', importable: false, help: 'Todos los valores de atributos juntos'),
        ];

        // Dynamic columns: one per Attribute defined in the tenant.
        // Path key uses the attribute:<id> prefix so exportValue() can resolve.
        foreach ($this->loadAttributes() as $attribute) {
            $fields[] = new FieldDefinition(
                key: "attribute:{$attribute->id}",
                label: $attribute->name,
                type: 'string',
                importable: false,
                help: 'Valor del atributo "'.$attribute->name.'" para esta variante',
            );
        }

        $fields[] = new FieldDefinition('created_at', 'Creado en', 'datetime', importable: false);
        $fields[] = new FieldDefinition('updated_at', 'Actualizado en', 'datetime', importable: false);

        return $fields;
    }

    public function query(): Builder
    {
        return ProductProduct::query()
            ->with([
                'product.category',
                'product.uom',
                'attributeValues.attribute',
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
                $q->where('sku', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%")
                    ->orWhereHas('product', function (Builder $p) use ($search): void {
                        $p->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if (! empty($filters['product_template_id'])) {
            $query->where('product_template_id', (int) $filters['product_template_id']);
        }

        if (! empty($filters['product_template_ids']) && is_array($filters['product_template_ids'])) {
            $query->whereIn('product_template_id', $filters['product_template_ids']);
        }

        if (! empty($filters['category_id'])) {
            $query->whereHas('product', function (Builder $p) use ($filters): void {
                $p->where('category_id', (int) $filters['category_id']);
            });
        }

        if (array_key_exists('is_principal', $filters) && $filters['is_principal'] !== null) {
            $query->where('is_principal', (bool) $filters['is_principal']);
        }

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null) {
            $query->whereHas('product', function (Builder $p) use ($filters): void {
                $p->where('is_active', (bool) $filters['is_active']);
            });
        }

        if (! empty($filters['ids']) && is_array($filters['ids'])) {
            $query->whereIn('id', $filters['ids']);
        }

        return $query;
    }

    /**
     * Resolve dynamic attribute columns and the computed stock attribute.
     */
    public function exportValue(Model $model, string $columnKey): mixed
    {
        if (str_starts_with($columnKey, 'attribute:')) {
            $attributeId = (int) substr($columnKey, strlen('attribute:'));
            $match = $model->attributeValues->firstWhere('attribute_id', $attributeId);
            return $this->normalizeScalar($match?->value);
        }

        return parent::exportValue($model, $columnKey);
    }

    /**
     * Cached per request — Attributes are stable during a single export run.
     *
     * @return \Illuminate\Support\Collection<int, Attribute>
     */
    private function loadAttributes(): \Illuminate\Support\Collection
    {
        return Attribute::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
