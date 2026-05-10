<?php

declare(strict_types=1);

namespace App\ImportExport\Schemas;

use App\ImportExport\Support\FieldDefinition;
use App\Models\LoyaltyTransaction;
use Illuminate\Database\Eloquent\Builder;

final class LoyaltyTransactionSchema extends AbstractResourceSchema
{
    public function key(): string
    {
        return 'loyalty_transaction';
    }

    public function label(): string
    {
        return 'Transacciones de lealtad';
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
            new FieldDefinition('type', 'Tipo', 'enum', importable: false, enumValues: ['earn', 'redeem', 'adjust', 'expire']),
            new FieldDefinition('points', 'Puntos', 'decimal', importable: false),
            new FieldDefinition('balance', 'Saldo después', 'decimal', importable: false),
            new FieldDefinition('description', 'Descripción', 'string', importable: false),
            new FieldDefinition('loyalty_program_id', 'ID programa', 'integer', importable: false),
            new FieldDefinition('program.name', 'Programa', 'relation', importable: false, relationResource: 'loyalty_program'),
            new FieldDefinition('loyalty_card_id', 'ID tarjeta', 'integer', importable: false),
            new FieldDefinition('card.code', 'Código tarjeta', 'relation', importable: false, relationResource: 'loyalty_card'),
            new FieldDefinition('partner_id', 'ID cliente', 'integer', importable: false),
            new FieldDefinition('partner.name', 'Cliente', 'relation', importable: false, relationResource: 'partner'),
            new FieldDefinition('partner.document_number', 'Documento cliente', 'relation', importable: false, relationResource: 'partner'),
            new FieldDefinition('source_type', 'Tipo de origen', 'string', importable: false, help: 'Modelo polimórfico (ej. Sale)'),
            new FieldDefinition('source_id', 'ID de origen', 'integer', importable: false),
            new FieldDefinition('expires_at', 'Vence', 'datetime', importable: false),
            new FieldDefinition('created_at', 'Creado en', 'datetime', importable: false),
            new FieldDefinition('updated_at', 'Actualizado en', 'datetime', importable: false),
        ];
    }

    public function query(): Builder
    {
        return LoyaltyTransaction::query()
            ->with(['program', 'card', 'partner']);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function applyFilters(Builder $query, array $filters): Builder
    {
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $q) use ($search): void {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhereHas('partner', function (Builder $p) use ($search): void {
                        $p->where('name', 'like', "%{$search}%")
                            ->orWhere('document_number', 'like', "%{$search}%");
                    })
                    ->orWhereHas('card', function (Builder $c) use ($search): void {
                        $c->where('code', 'like', "%{$search}%");
                    });
            });
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['partner_id'])) {
            $query->where('partner_id', (int) $filters['partner_id']);
        }

        if (! empty($filters['loyalty_program_id'])) {
            $query->where('loyalty_program_id', (int) $filters['loyalty_program_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        if (! empty($filters['ids']) && is_array($filters['ids'])) {
            $query->whereIn('id', $filters['ids']);
        }

        return $query;
    }
}
