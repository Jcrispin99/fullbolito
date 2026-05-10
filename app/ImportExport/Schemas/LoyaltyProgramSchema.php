<?php

declare(strict_types=1);

namespace App\ImportExport\Schemas;

use App\ImportExport\Support\FieldDefinition;
use App\Models\LoyaltyProgram;
use Illuminate\Database\Eloquent\Builder;

final class LoyaltyProgramSchema extends AbstractResourceSchema
{
    public function key(): string
    {
        return 'loyalty_program';
    }

    public function label(): string
    {
        return 'Programas de lealtad';
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
            new FieldDefinition('name', 'Nombre', 'string', required: true, importable: false),
            new FieldDefinition('description', 'Descripción', 'string', importable: false),
            new FieldDefinition('program_type', 'Tipo de programa', 'string', importable: false),
            new FieldDefinition('point_name', 'Nombre del punto', 'string', importable: false),
            new FieldDefinition('applies_on', 'Aplica en', 'string', importable: false),
            new FieldDefinition('trigger', 'Disparador', 'string', importable: false),
            new FieldDefinition('is_pos', 'Disponible en POS', 'boolean', importable: false),
            new FieldDefinition('is_web', 'Disponible en Web', 'boolean', importable: false),
            new FieldDefinition('is_sales', 'Disponible en Ventas', 'boolean', importable: false),
            new FieldDefinition('starts_at', 'Inicio', 'datetime', importable: false),
            new FieldDefinition('ends_at', 'Fin', 'datetime', importable: false),
            new FieldDefinition('max_uses', 'Usos máximos', 'integer', importable: false),
            new FieldDefinition('max_uses_per_customer', 'Usos máx. por cliente', 'integer', importable: false),
            new FieldDefinition('current_uses', 'Usos actuales', 'integer', importable: false),
            new FieldDefinition('cards_count', 'Tarjetas emitidas', 'integer', importable: false),
            new FieldDefinition('transactions_count', 'Transacciones', 'integer', importable: false),
            new FieldDefinition('rules_count', 'Reglas', 'integer', importable: false),
            new FieldDefinition('rewards_count', 'Recompensas', 'integer', importable: false),
            new FieldDefinition('is_active', 'Activo', 'boolean', importable: false),
            new FieldDefinition('created_at', 'Creado en', 'datetime', importable: false),
            new FieldDefinition('updated_at', 'Actualizado en', 'datetime', importable: false),
        ];
    }

    public function query(): Builder
    {
        return LoyaltyProgram::query()
            ->withCount(['cards', 'transactions', 'rules', 'rewards']);
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
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['program_type'])) {
            $query->where('program_type', $filters['program_type']);
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
