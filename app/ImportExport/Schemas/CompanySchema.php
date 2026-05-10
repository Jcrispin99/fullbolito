<?php

declare(strict_types=1);

namespace App\ImportExport\Schemas;

use App\ImportExport\Support\FieldDefinition;
use App\Models\Company;
use Illuminate\Database\Eloquent\Builder;

final class CompanySchema extends AbstractResourceSchema
{
    public function key(): string
    {
        return 'company';
    }

    public function label(): string
    {
        return 'Empresas';
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
            new FieldDefinition('business_name', 'Razón social', 'string', required: true, importable: false),
            new FieldDefinition('trade_name', 'Nombre comercial', 'string', importable: false),
            new FieldDefinition('ruc', 'RUC', 'string', importable: false),
            new FieldDefinition('address', 'Dirección', 'string', importable: false),
            new FieldDefinition('phone', 'Teléfono', 'string', importable: false),
            new FieldDefinition('email', 'Email', 'string', importable: false),
            new FieldDefinition('ubigeo', 'Ubigeo', 'string', importable: false),
            new FieldDefinition('branch_code', 'Código sucursal', 'string', importable: false),
            new FieldDefinition('is_main', 'Es principal', 'boolean', importable: false),
            new FieldDefinition('parent_id', 'ID empresa padre', 'integer', importable: false),
            new FieldDefinition('parent.business_name', 'Empresa padre', 'relation', importable: false, relationResource: 'company'),
            new FieldDefinition('branches_count', 'Sucursales', 'integer', importable: false, help: 'Cantidad de sucursales hijas'),
            new FieldDefinition('users_count', 'Usuarios', 'integer', importable: false),
            new FieldDefinition('brand_color', 'Color de marca', 'string', importable: false),
            new FieldDefinition('invoice_footer', 'Pie de factura', 'string', importable: false),
            new FieldDefinition('is_active', 'Activo', 'boolean', importable: false),
            new FieldDefinition('created_at', 'Creado en', 'datetime', importable: false),
            new FieldDefinition('updated_at', 'Actualizado en', 'datetime', importable: false),
        ];
    }

    public function query(): Builder
    {
        return Company::query()
            ->with('parent')
            ->withCount(['branches', 'users']);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function applyFilters(Builder $query, array $filters): Builder
    {
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $q) use ($search): void {
                $q->where('business_name', 'like', "%{$search}%")
                    ->orWhere('trade_name', 'like', "%{$search}%")
                    ->orWhere('ruc', 'like', "%{$search}%");
            });
        }

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        if (array_key_exists('is_main', $filters) && $filters['is_main'] !== null) {
            $query->where('is_main', (bool) $filters['is_main']);
        }

        if (! empty($filters['ids']) && is_array($filters['ids'])) {
            $query->whereIn('id', $filters['ids']);
        }

        return $query;
    }
}
