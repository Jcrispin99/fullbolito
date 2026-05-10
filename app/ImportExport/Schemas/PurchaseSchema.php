<?php

declare(strict_types=1);

namespace App\ImportExport\Schemas;

use App\ImportExport\Support\FieldDefinition;
use App\Models\Purchase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class PurchaseSchema extends AbstractResourceSchema
{
    public function key(): string
    {
        return 'purchase';
    }

    public function label(): string
    {
        return 'Compras';
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
            new FieldDefinition('serie', 'Serie', 'string', importable: false),
            new FieldDefinition('correlative', 'Correlativo', 'string', importable: false),
            new FieldDefinition('date', 'Fecha', 'datetime', importable: false),

            new FieldDefinition('partner_id', 'ID proveedor', 'integer', importable: false),
            new FieldDefinition('partner.name', 'Proveedor', 'relation', importable: false, relationResource: 'partner'),
            new FieldDefinition('partner.document_type', 'Tipo doc proveedor', 'relation', importable: false),
            new FieldDefinition('partner.document_number', 'RUC/Doc proveedor', 'relation', importable: false),

            new FieldDefinition('vendor_bill_number', 'Número factura proveedor', 'string', importable: false),
            new FieldDefinition('vendor_bill_date', 'Fecha factura proveedor', 'date', importable: false),

            new FieldDefinition('warehouse_id', 'ID almacén', 'integer', importable: false),
            new FieldDefinition('warehouse.name', 'Almacén', 'relation', importable: false, relationResource: 'warehouse'),
            new FieldDefinition('company_id', 'ID empresa', 'integer', importable: false),
            new FieldDefinition('company.business_name', 'Empresa', 'relation', importable: false, relationResource: 'company'),

            new FieldDefinition('journal_id', 'ID journal', 'integer', importable: false),
            new FieldDefinition('journal.name', 'Tipo de comprobante', 'relation', importable: false),

            new FieldDefinition('buyer_id', 'ID comprador', 'integer', importable: false),
            new FieldDefinition('buyer.name', 'Comprador', 'relation', importable: false),

            new FieldDefinition('total', 'Total', 'decimal', importable: false),
            new FieldDefinition('status', 'Estado', 'enum', importable: false),
            new FieldDefinition('payment_status', 'Estado de pago', 'enum', importable: false),

            new FieldDefinition('lines_count', 'Cantidad de líneas', 'integer', importable: false),
            new FieldDefinition('observation', 'Observación', 'string', importable: false),

            new FieldDefinition('created_at', 'Creado en', 'datetime', importable: false),
            new FieldDefinition('updated_at', 'Actualizado en', 'datetime', importable: false),
        ];
    }

    public function query(): Builder
    {
        return Purchase::query()
            ->companyFiltered()
            ->with([
                'partner',
                'warehouse',
                'company',
                'journal',
                'buyer',
            ])
            ->withCount('productables');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function applyFilters(Builder $query, array $filters): Builder
    {
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $q) use ($search): void {
                $q->where('serie', 'like', "%{$search}%")
                    ->orWhere('correlative', 'like', "%{$search}%")
                    ->orWhere('vendor_bill_number', 'like', "%{$search}%")
                    ->orWhereHas('partner', function (Builder $p) use ($search): void {
                        $p->where('name', 'like', "%{$search}%")
                            ->orWhere('document_number', 'like', "%{$search}%");
                    });
            });
        }

        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['payment_status']) && $filters['payment_status'] !== 'all') {
            $query->where('payment_status', $filters['payment_status']);
        }

        if (! empty($filters['partner_id'])) {
            $query->where('partner_id', (int) $filters['partner_id']);
        }

        if (! empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', (int) $filters['warehouse_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('date', '<=', $filters['date_to']);
        }

        if (! empty($filters['ids']) && is_array($filters['ids'])) {
            $query->whereIn('id', $filters['ids']);
        }

        return $query;
    }

    /**
     * Map computed `lines_count` to the productables_count attribute populated by withCount.
     */
    public function exportValue(Model $model, string $columnKey): mixed
    {
        if ($columnKey === 'lines_count') {
            return $this->normalizeScalar($model->getAttribute('productables_count'));
        }

        return parent::exportValue($model, $columnKey);
    }
}
