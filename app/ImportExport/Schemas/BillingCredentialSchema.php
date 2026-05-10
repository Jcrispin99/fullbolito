<?php

declare(strict_types=1);

namespace App\ImportExport\Schemas;

use App\ImportExport\Support\FieldDefinition;
use App\Models\BillingCredential;
use Illuminate\Database\Eloquent\Builder;

final class BillingCredentialSchema extends AbstractResourceSchema
{
    public function key(): string
    {
        return 'billing_credential';
    }

    public function label(): string
    {
        return 'Credenciales de facturación';
    }

    public function supportsImport(): bool
    {
        return false;
    }

    /**
     * Sensitive fields (sol_pass, client_secret, cert_path) are deliberately
     * NOT exposed for export. Only metadata useful for auditing.
     *
     * @return array<int, FieldDefinition>
     */
    public function fields(): array
    {
        return [
            new FieldDefinition('id', 'ID', 'integer', importable: false),
            new FieldDefinition('name', 'Nombre', 'string', required: true, importable: false),
            new FieldDefinition('sol_user', 'Usuario SOL', 'string', importable: false),
            new FieldDefinition('client_id', 'Client ID', 'string', importable: false),
            new FieldDefinition('production', 'Producción', 'boolean', importable: false, help: 'true = productivo, false = pruebas'),
            new FieldDefinition('is_active', 'Activo', 'boolean', importable: false),
            new FieldDefinition('created_at', 'Creado en', 'datetime', importable: false),
            new FieldDefinition('updated_at', 'Actualizado en', 'datetime', importable: false),
        ];
    }

    public function query(): Builder
    {
        return BillingCredential::query();
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
                    ->orWhere('sol_user', 'like', "%{$search}%");
            });
        }

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        if (array_key_exists('production', $filters) && $filters['production'] !== null) {
            $query->where('production', (bool) $filters['production']);
        }

        if (! empty($filters['ids']) && is_array($filters['ids'])) {
            $query->whereIn('id', $filters['ids']);
        }

        return $query;
    }
}
