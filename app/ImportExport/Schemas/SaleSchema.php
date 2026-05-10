<?php

declare(strict_types=1);

namespace App\ImportExport\Schemas;

use App\ImportExport\Support\FieldDefinition;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class SaleSchema extends AbstractResourceSchema
{
    public function key(): string
    {
        return 'sale';
    }

    public function label(): string
    {
        return 'Ventas';
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
            new FieldDefinition('document_number', 'Documento', 'string', importable: false, help: 'Serie-Correlativo concatenado'),
            new FieldDefinition('serie', 'Serie', 'string', importable: false),
            new FieldDefinition('correlative', 'Correlativo', 'string', importable: false),
            new FieldDefinition('date', 'Fecha', 'datetime', importable: false),

            new FieldDefinition('partner_id', 'ID cliente', 'integer', importable: false),
            new FieldDefinition('partner.name', 'Cliente', 'relation', importable: false, relationResource: 'partner'),
            new FieldDefinition('partner.document_type', 'Tipo doc cliente', 'relation', importable: false),
            new FieldDefinition('partner.document_number', 'Doc cliente', 'relation', importable: false),
            new FieldDefinition('partner.email', 'Email cliente', 'relation', importable: false),

            new FieldDefinition('warehouse_id', 'ID almacén', 'integer', importable: false),
            new FieldDefinition('warehouse.name', 'Almacén', 'relation', importable: false, relationResource: 'warehouse'),
            new FieldDefinition('company_id', 'ID empresa', 'integer', importable: false),
            new FieldDefinition('company.business_name', 'Empresa', 'relation', importable: false, relationResource: 'company'),

            new FieldDefinition('journal_id', 'ID journal', 'integer', importable: false),
            new FieldDefinition('journal.name', 'Tipo de comprobante', 'relation', importable: false),

            new FieldDefinition('user_id', 'ID vendedor', 'integer', importable: false),
            new FieldDefinition('user.name', 'Vendedor', 'relation', importable: false),

            new FieldDefinition('subtotal', 'Subtotal', 'decimal', importable: false),
            new FieldDefinition('tax_amount', 'Impuesto', 'decimal', importable: false),
            new FieldDefinition('total', 'Total', 'decimal', importable: false),

            new FieldDefinition('status', 'Estado', 'enum', importable: false),
            new FieldDefinition('payment_status', 'Estado de pago', 'enum', importable: false),
            new FieldDefinition('sunat_status', 'Estado SUNAT', 'string', importable: false),
            new FieldDefinition('sunat_sent_at', 'Enviado a SUNAT', 'datetime', importable: false),

            new FieldDefinition('original_sale_id', 'ID venta original', 'integer', importable: false, help: 'Para notas de crédito'),
            new FieldDefinition('pos_session_id', 'ID sesión POS', 'integer', importable: false),

            new FieldDefinition('lines_count', 'Cantidad de líneas', 'integer', importable: false),
            new FieldDefinition('notes', 'Notas', 'string', importable: false),

            new FieldDefinition('created_at', 'Creado en', 'datetime', importable: false),
            new FieldDefinition('updated_at', 'Actualizado en', 'datetime', importable: false),
        ];
    }

    public function query(): Builder
    {
        return Sale::query()
            ->companyFiltered()
            ->with([
                'partner',
                'warehouse',
                'company',
                'journal',
                'user',
            ])
            ->withCount('products');
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
     * Map computed `lines_count` to the products_count attribute populated by withCount.
     */
    public function exportValue(Model $model, string $columnKey): mixed
    {
        if ($columnKey === 'lines_count') {
            return $this->normalizeScalar($model->getAttribute('products_count'));
        }

        return parent::exportValue($model, $columnKey);
    }
}
