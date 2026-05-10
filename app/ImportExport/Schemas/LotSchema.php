<?php

declare(strict_types=1);

namespace App\ImportExport\Schemas;

use App\ImportExport\Support\FieldDefinition;
use App\Models\Lot;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class LotSchema extends AbstractResourceSchema
{
    public function key(): string
    {
        return 'lot';
    }

    public function label(): string
    {
        return 'Lotes';
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
            new FieldDefinition('lot_number', 'Número de lote', 'string', required: true, importable: false),
            new FieldDefinition('product_product_id', 'ID variante', 'integer', importable: false),
            new FieldDefinition('productProduct.sku', 'SKU', 'relation', importable: false, relationResource: 'product_variant'),
            new FieldDefinition('productProduct.barcode', 'Código de barras', 'relation', importable: false, relationResource: 'product_variant'),
            new FieldDefinition('productProduct.product.name', 'Producto', 'relation', importable: false, relationResource: 'product'),
            new FieldDefinition('manufactured_at', 'Fabricación', 'date', importable: false),
            new FieldDefinition('expires_at', 'Vencimiento', 'date', importable: false),
            new FieldDefinition('initial_quantity', 'Cantidad inicial', 'decimal', importable: false),
            new FieldDefinition('initial_cost', 'Costo inicial', 'decimal', importable: false),
            new FieldDefinition('current_stock', 'Stock actual', 'decimal', importable: false, help: 'Suma de quantity_balance en lotInventories'),
            new FieldDefinition('supplier_id', 'ID proveedor', 'integer', importable: false),
            new FieldDefinition('supplier.name', 'Proveedor', 'relation', importable: false, relationResource: 'partner'),
            new FieldDefinition('supplier.document_number', 'RUC/Doc proveedor', 'relation', importable: false, relationResource: 'partner'),
            new FieldDefinition('purchase_id', 'ID compra origen', 'integer', importable: false),
            new FieldDefinition('purchase.serie', 'Serie compra', 'relation', importable: false),
            new FieldDefinition('purchase.correlative', 'Correlativo compra', 'relation', importable: false),
            new FieldDefinition('company_id', 'ID empresa', 'integer', importable: false),
            new FieldDefinition('company.business_name', 'Empresa', 'relation', importable: false, relationResource: 'company'),
            new FieldDefinition('status', 'Estado', 'enum', importable: false, enumValues: ['active', 'inactive', 'expired', 'recalled']),
            new FieldDefinition('notes', 'Notas', 'string', importable: false),
            new FieldDefinition('created_at', 'Creado en', 'datetime', importable: false),
            new FieldDefinition('updated_at', 'Actualizado en', 'datetime', importable: false),
        ];
    }

    public function query(): Builder
    {
        return Lot::query()
            ->companyFiltered()
            ->with([
                'productProduct.product',
                'supplier',
                'purchase',
                'company',
                'lotInventories',
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
                $q->where('lot_number', 'like', "%{$search}%")
                    ->orWhereHas('productProduct', function (Builder $v) use ($search): void {
                        $v->where('sku', 'like', "%{$search}%")
                            ->orWhere('barcode', 'like', "%{$search}%");
                    });
            });
        }

        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['product_product_id'])) {
            $query->where('product_product_id', (int) $filters['product_product_id']);
        }

        if (! empty($filters['supplier_id'])) {
            $query->where('supplier_id', (int) $filters['supplier_id']);
        }

        if (! empty($filters['ids']) && is_array($filters['ids'])) {
            $query->whereIn('id', $filters['ids']);
        }

        return $query;
    }

    /**
     * Compute current_stock from eager-loaded lotInventories without N+1.
     */
    public function exportValue(Model $model, string $columnKey): mixed
    {
        if ($columnKey === 'current_stock') {
            $total = 0.0;
            foreach ($model->lotInventories as $inv) {
                $total += (float) ($inv->quantity_balance ?? 0);
            }
            return $this->normalizeScalar($total);
        }

        return parent::exportValue($model, $columnKey);
    }
}
