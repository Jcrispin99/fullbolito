<?php

declare(strict_types=1);

namespace App\ImportExport\Contracts;

use App\ImportExport\Support\FieldDefinition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

interface ResourceSchema
{
    public function key(): string;

    public function label(): string;

    /**
     * Whether the resource supports importing from a file.
     * Defaults to true; resources should override and return false
     * if they only allow exporting.
     */
    public function supportsImport(): bool;

    /**
     * @return array<int, FieldDefinition>
     */
    public function fields(): array;

    /**
     * Base query used for export. Should already apply tenant/company scoping.
     */
    public function query(): Builder;

    /**
     * Apply request filters (search, status, etc.) to the export query.
     *
     * @param  array<string, mixed>  $filters
     */
    public function applyFilters(Builder $query, array $filters): Builder;

    /**
     * Extract a single column value from a model. Supports dot-notation
     * (e.g. "company.name") via the schema's own conventions.
     */
    public function exportValue(Model $model, string $columnKey): mixed;

    /**
     * Validate a single mapped row before persistence.
     *
     * @param  array<string, mixed>  $row  Field key => value
     * @return array<int, string> Empty array on success, list of error messages on failure.
     */
    public function validateRow(array $row, string $mode): array;

    /**
     * Persist a single mapped row according to the import mode.
     *
     * @param  array<string, mixed>  $row  Field key => value
     */
    public function persistRow(array $row, string $mode, ?string $uniqueKey = null): Model;

    /**
     * The default unique-key column used by update/upsert when the import
     * job does not specify one. Returning null falls back to 'id'.
     */
    public function defaultUniqueKey(): ?string;

    /**
     * Eloquent class backing this schema. Used by the generic engine and
     * by other schemas when resolving FKs that point to this resource.
     *
     * @return class-string<Model>
     */
    public function modelClass(): string;
}
