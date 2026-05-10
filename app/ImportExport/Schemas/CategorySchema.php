<?php

declare(strict_types=1);

namespace App\ImportExport\Schemas;

use App\ImportExport\Support\FieldDefinition;
use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;

final class CategorySchema extends AbstractResourceSchema
{
    public function key(): string
    {
        return 'category';
    }

    public function label(): string
    {
        return 'Categorías';
    }

    public function defaultUniqueKey(): ?string
    {
        return 'name';
    }

    /**
     * @return array<int, FieldDefinition>
     */
    public function fields(): array
    {
        return [
            new FieldDefinition('id', 'ID', 'integer', importable: false),
            new FieldDefinition('name', 'Nombre', 'string', required: true, unique: true),
            new FieldDefinition('full_name', 'Nombre completo', 'string', importable: false, help: 'Auto-calculado a partir de la jerarquía padre / hijo'),
            new FieldDefinition('description', 'Descripción', 'string'),
            new FieldDefinition(
                'parent_id',
                'Categoría padre',
                'integer',
                relationResource: 'category',
                help: 'Acepta ID numérico o nombre de la categoría padre',
                relationMatchBy: ['id', 'name'],
            ),
            new FieldDefinition('parent.name', 'Categoría padre (nombre)', 'relation', importable: false, relationResource: 'category'),
            new FieldDefinition('parent.full_name', 'Ruta padre', 'relation', importable: false, relationResource: 'category'),
            new FieldDefinition('children_count', 'Subcategorías', 'integer', importable: false),
            new FieldDefinition('products_count', 'Productos en categoría', 'integer', importable: false),
            new FieldDefinition('is_active', 'Activo', 'boolean'),
            new FieldDefinition('created_at', 'Creado en', 'datetime', importable: false),
            new FieldDefinition('updated_at', 'Actualizado en', 'datetime', importable: false),
        ];
    }

    public function query(): Builder
    {
        return Category::query()
            ->with('parent')
            ->withCount(['children', 'products']);
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
                    ->orWhere('full_name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
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
