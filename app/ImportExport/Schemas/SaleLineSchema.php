<?php

declare(strict_types=1);

namespace App\ImportExport\Schemas;

use App\ImportExport\Support\FieldDefinition;
use App\Models\Productable;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Builder;

final class SaleLineSchema extends AbstractResourceSchema
{
    public function key(): string
    {
        return 'sale_line';
    }

    public function label(): string
    {
        return 'Líneas de venta';
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
            new FieldDefinition('id', 'ID línea', 'integer', importable: false),

            new FieldDefinition('productable_id', 'ID venta', 'integer', importable: false),
            new FieldDefinition('productable.serie', 'Serie', 'relation', importable: false),
            new FieldDefinition('productable.correlative', 'Correlativo', 'relation', importable: false),
            new FieldDefinition('productable.date', 'Fecha', 'datetime', importable: false),
            new FieldDefinition('productable.status', 'Estado venta', 'string', importable: false),
            new FieldDefinition('productable.payment_status', 'Estado pago', 'string', importable: false),

            new FieldDefinition('productable.partner.name', 'Cliente', 'relation', importable: false, relationResource: 'partner'),
            new FieldDefinition('productable.partner.document_number', 'Doc cliente', 'relation', importable: false),
            new FieldDefinition('productable.warehouse.name', 'Almacén', 'relation', importable: false, relationResource: 'warehouse'),
            new FieldDefinition('productable.company.business_name', 'Empresa', 'relation', importable: false, relationResource: 'company'),
            new FieldDefinition('productable.journal.name', 'Tipo comprobante', 'relation', importable: false),
            new FieldDefinition('productable.user.name', 'Vendedor', 'relation', importable: false),

            new FieldDefinition('product_product_id', 'ID variante', 'integer', importable: false),
            new FieldDefinition('productProduct.sku', 'SKU', 'relation', importable: false),
            new FieldDefinition('productProduct.barcode', 'Código de barras', 'relation', importable: false),
            new FieldDefinition('productProduct.template.name', 'Producto', 'relation', importable: false, relationResource: 'product'),
            new FieldDefinition('productProduct.display_name', 'Nombre + atributos', 'relation', importable: false, help: 'Producto con sus atributos concatenados'),
            new FieldDefinition('productProduct.attribute_string', 'Atributos', 'relation', importable: false),

            new FieldDefinition('uom.name', 'Unidad', 'relation', importable: false),
            new FieldDefinition('uom_factor', 'Factor UoM', 'decimal', importable: false),

            new FieldDefinition('quantity', 'Cantidad', 'decimal', importable: false),
            new FieldDefinition('quantity_uom', 'Cantidad (UoM venta)', 'decimal', importable: false),
            new FieldDefinition('price', 'Precio unit.', 'decimal', importable: false),
            new FieldDefinition('price_uom', 'Precio (UoM venta)', 'decimal', importable: false),
            new FieldDefinition('subtotal', 'Subtotal', 'decimal', importable: false),
            new FieldDefinition('tax.name', 'Impuesto', 'relation', importable: false),
            new FieldDefinition('tax_rate', 'Tasa impuesto', 'decimal', importable: false),
            new FieldDefinition('tax_amount', 'Monto impuesto', 'decimal', importable: false),
            new FieldDefinition('total', 'Total línea', 'decimal', importable: false),

            new FieldDefinition('lot.lot_number', 'Lote', 'relation', importable: false),
            new FieldDefinition('lot.expires_at', 'Vence', 'date', importable: false),
            new FieldDefinition('lot_number_input', 'Lote ingresado', 'string', importable: false),
            new FieldDefinition('lot_manufactured_at', 'Fabricación', 'date', importable: false),
            new FieldDefinition('lot_expires_at', 'Vencimiento (línea)', 'date', importable: false),

            new FieldDefinition('created_at', 'Creado en', 'datetime', importable: false),
            new FieldDefinition('updated_at', 'Actualizado en', 'datetime', importable: false),
        ];
    }

    public function query(): Builder
    {
        return Productable::query()
            ->where('productable_type', Sale::class)
            ->whereHasMorph('productable', [Sale::class], function (Builder $q): void {
                $q->companyFiltered();
            })
            ->with([
                'productProduct.template',
                'productProduct.attributeValues',
                'uom',
                'tax',
                'lot',
                'productable' => function ($morphTo): void {
                    $morphTo->morphWith([
                        Sale::class => ['partner', 'warehouse', 'company', 'journal', 'user'],
                    ]);
                },
            ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function applyFilters(Builder $query, array $filters): Builder
    {
        if (! empty($filters['sale_id'])) {
            $query->where('productable_id', (int) $filters['sale_id']);
        }

        if (! empty($filters['ids']) && is_array($filters['ids'])) {
            $query->whereIn('id', $filters['ids']);
        }

        if (! empty($filters['product_product_id'])) {
            $query->where('product_product_id', (int) $filters['product_product_id']);
        }

        $hasParentFilter = ! empty($filters['search'])
            || ! empty($filters['status'])
            || ! empty($filters['partner_id'])
            || ! empty($filters['warehouse_id'])
            || ! empty($filters['date_from'])
            || ! empty($filters['date_to']);

        if ($hasParentFilter) {
            $query->whereHasMorph('productable', [Sale::class], function (Builder $q) use ($filters): void {
                if (! empty($filters['search'])) {
                    $search = $filters['search'];
                    $q->where(function (Builder $sq) use ($search): void {
                        $sq->where('serie', 'like', "%{$search}%")
                            ->orWhere('correlative', 'like', "%{$search}%");
                    });
                }

                if (! empty($filters['status']) && $filters['status'] !== 'all') {
                    $q->where('status', $filters['status']);
                }

                if (! empty($filters['partner_id'])) {
                    $q->where('partner_id', (int) $filters['partner_id']);
                }

                if (! empty($filters['warehouse_id'])) {
                    $q->where('warehouse_id', (int) $filters['warehouse_id']);
                }

                if (! empty($filters['date_from'])) {
                    $q->whereDate('date', '>=', $filters['date_from']);
                }

                if (! empty($filters['date_to'])) {
                    $q->whereDate('date', '<=', $filters['date_to']);
                }
            });
        }

        return $query;
    }
}
