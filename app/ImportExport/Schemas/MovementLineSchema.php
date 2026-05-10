<?php

declare(strict_types=1);

namespace App\ImportExport\Schemas;

use App\ImportExport\Support\FieldDefinition;
use App\Models\Movement;
use App\Models\Productable;
use Illuminate\Database\Eloquent\Builder;

final class MovementLineSchema extends AbstractResourceSchema
{
    public function key(): string
    {
        return 'movement_line';
    }

    public function label(): string
    {
        return 'Líneas de movimiento';
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

            new FieldDefinition('productable_id', 'ID movimiento', 'integer', importable: false),
            new FieldDefinition('productable.serie', 'Serie', 'relation', importable: false),
            new FieldDefinition('productable.correlative', 'Correlativo', 'relation', importable: false),
            new FieldDefinition('productable.date', 'Fecha', 'datetime', importable: false),
            new FieldDefinition('productable.type', 'Tipo', 'enum', importable: false, enumValues: ['entry', 'exit']),
            new FieldDefinition('productable.status', 'Estado', 'string', importable: false),
            new FieldDefinition('productable.reason', 'Motivo', 'string', importable: false),
            new FieldDefinition('productable.transfer_id', 'ID transferencia', 'integer', importable: false, help: 'Vacío si es movimiento suelto'),

            new FieldDefinition('productable.warehouse.name', 'Almacén', 'relation', importable: false, relationResource: 'warehouse'),
            new FieldDefinition('productable.company.business_name', 'Empresa', 'relation', importable: false, relationResource: 'company'),
            new FieldDefinition('productable.journal.name', 'Tipo comprobante', 'relation', importable: false),
            new FieldDefinition('productable.createdUser.name', 'Creado por', 'relation', importable: false),
            new FieldDefinition('productable.postedUser.name', 'Confirmado por', 'relation', importable: false),

            new FieldDefinition('product_product_id', 'ID variante', 'integer', importable: false),
            new FieldDefinition('productProduct.sku', 'SKU', 'relation', importable: false),
            new FieldDefinition('productProduct.barcode', 'Código de barras', 'relation', importable: false),
            new FieldDefinition('productProduct.template.name', 'Producto', 'relation', importable: false, relationResource: 'product'),
            new FieldDefinition('productProduct.display_name', 'Nombre + atributos', 'relation', importable: false),
            new FieldDefinition('productProduct.attribute_string', 'Atributos', 'relation', importable: false),

            new FieldDefinition('uom.name', 'Unidad', 'relation', importable: false),
            new FieldDefinition('uom_factor', 'Factor UoM', 'decimal', importable: false),

            new FieldDefinition('quantity', 'Cantidad', 'decimal', importable: false),
            new FieldDefinition('quantity_uom', 'Cantidad (UoM ingreso)', 'decimal', importable: false),
            new FieldDefinition('price', 'Precio/Costo unit.', 'decimal', importable: false),
            new FieldDefinition('price_uom', 'Precio/Costo (UoM)', 'decimal', importable: false),
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
            ->where('productable_type', Movement::class)
            ->whereHasMorph('productable', [Movement::class], function (Builder $q): void {
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
                        Movement::class => ['warehouse', 'company', 'journal', 'createdUser', 'postedUser'],
                    ]);
                },
            ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function applyFilters(Builder $query, array $filters): Builder
    {
        if (! empty($filters['movement_id'])) {
            $query->where('productable_id', (int) $filters['movement_id']);
        }

        if (! empty($filters['ids']) && is_array($filters['ids'])) {
            $query->whereIn('id', $filters['ids']);
        }

        if (! empty($filters['product_product_id'])) {
            $query->where('product_product_id', (int) $filters['product_product_id']);
        }

        $hasParentFilter = ! empty($filters['search'])
            || ! empty($filters['type'])
            || ! empty($filters['status'])
            || ! empty($filters['warehouse_id'])
            || ! empty($filters['date_from'])
            || ! empty($filters['date_to'])
            || array_key_exists('standalone', $filters);

        if ($hasParentFilter) {
            $query->whereHasMorph('productable', [Movement::class], function (Builder $q) use ($filters): void {
                if (! empty($filters['search'])) {
                    $search = $filters['search'];
                    $q->where(function (Builder $sq) use ($search): void {
                        $sq->where('serie', 'like', "%{$search}%")
                            ->orWhere('correlative', 'like', "%{$search}%")
                            ->orWhere('reason', 'like', "%{$search}%");
                    });
                }

                if (! empty($filters['type']) && $filters['type'] !== 'all') {
                    $q->where('type', $filters['type']);
                }

                if (! empty($filters['status']) && $filters['status'] !== 'all') {
                    $q->where('status', $filters['status']);
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

                if (array_key_exists('standalone', $filters) && $filters['standalone'] !== null) {
                    if ($filters['standalone']) {
                        $q->whereNull('transfer_id');
                    } else {
                        $q->whereNotNull('transfer_id');
                    }
                }
            });
        }

        return $query;
    }
}
