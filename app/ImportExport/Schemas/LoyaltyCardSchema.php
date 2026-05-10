<?php

declare(strict_types=1);

namespace App\ImportExport\Schemas;

use App\ImportExport\Support\FieldDefinition;
use App\Models\LoyaltyCard;
use Illuminate\Database\Eloquent\Builder;

final class LoyaltyCardSchema extends AbstractResourceSchema
{
    public function key(): string
    {
        return 'loyalty_card';
    }

    public function label(): string
    {
        return 'Tarjetas de lealtad';
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
            new FieldDefinition('code', 'Código', 'string', required: true, importable: false),
            new FieldDefinition('loyalty_program_id', 'ID programa', 'integer', importable: false),
            new FieldDefinition('program.name', 'Programa', 'relation', importable: false, relationResource: 'loyalty_program'),
            new FieldDefinition('partner_id', 'ID cliente', 'integer', importable: false),
            new FieldDefinition('partner.name', 'Cliente', 'relation', importable: false, relationResource: 'partner'),
            new FieldDefinition('partner.document_number', 'Documento cliente', 'relation', importable: false, relationResource: 'partner'),
            new FieldDefinition('points', 'Puntos', 'decimal', importable: false),
            new FieldDefinition('expiration_date', 'Fecha de expiración', 'date', importable: false),
            new FieldDefinition('is_active', 'Activo', 'boolean', importable: false),
            new FieldDefinition('created_at', 'Creado en', 'datetime', importable: false),
            new FieldDefinition('updated_at', 'Actualizado en', 'datetime', importable: false),
        ];
    }

    public function query(): Builder
    {
        return LoyaltyCard::query()->with(['program', 'partner']);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function applyFilters(Builder $query, array $filters): Builder
    {
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $q) use ($search): void {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhereHas('partner', function (Builder $p) use ($search): void {
                        $p->where('name', 'like', "%{$search}%")
                            ->orWhere('document_number', 'like', "%{$search}%");
                    });
            });
        }

        if (! empty($filters['loyalty_program_id'])) {
            $query->where('loyalty_program_id', (int) $filters['loyalty_program_id']);
        }

        if (! empty($filters['partner_id'])) {
            $query->where('partner_id', (int) $filters['partner_id']);
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
