<?php

declare(strict_types=1);

namespace App\ImportExport\Schemas;

use App\ImportExport\Contracts\ResourceSchema;
use App\ImportExport\ImportExportRegistry;
use App\ImportExport\Support\FieldDefinition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

abstract class AbstractResourceSchema implements ResourceSchema
{
    /**
     * @return array<int, FieldDefinition>
     */
    abstract public function fields(): array;

    abstract public function query(): Builder;

    public function label(): string
    {
        return ucfirst(str_replace('_', ' ', $this->key()));
    }

    public function supportsImport(): bool
    {
        return true;
    }

    /**
     * Default filter implementation: no-op. Schemas can override.
     *
     * @param  array<string, mixed>  $filters
     */
    public function applyFilters(Builder $query, array $filters): Builder
    {
        return $query;
    }

    /**
     * Default export value resolver: walks dot-notation paths through
     * model attributes and relations.
     *
     * Supports the following special segments on collections:
     *  - `*`         iterate every item; remaining path is applied per item,
     *                results are concatenated with the configured separator.
     *  - `count`     return the number of items.
     *  - `first`     keep walking on the first item.
     *  - `principal` keep walking on the item where `is_principal = true`
     *                (falls back to first if none).
     */
    public function exportValue(Model $model, string $columnKey): mixed
    {
        return $this->normalizeScalar(
            $this->resolvePath($model, explode('.', $columnKey)),
        );
    }

    /**
     * @param  array<int, string>  $segments
     */
    protected function resolvePath(mixed $value, array $segments): mixed
    {
        foreach ($segments as $i => $segment) {
            if ($value === null) {
                return null;
            }

            if ($value instanceof Collection || $value instanceof EloquentCollection) {
                if ($segment === 'count') {
                    return $value->count();
                }

                if ($segment === '*') {
                    $remaining = array_slice($segments, $i + 1);
                    $items = [];
                    foreach ($value as $item) {
                        $resolved = $remaining === []
                            ? $item
                            : $this->resolvePath($item, $remaining);
                        $scalar = $this->normalizeScalar($resolved);
                        if ($scalar === null || $scalar === '') {
                            continue;
                        }
                        $items[] = (string) $scalar;
                    }
                    return implode($this->multiSeparator(), $items);
                }

                if ($segment === 'first') {
                    $value = $value->first();
                    continue;
                }

                if ($segment === 'principal') {
                    $value = $value->firstWhere('is_principal', true) ?? $value->first();
                    continue;
                }

                return null;
            }

            if ($value instanceof Model) {
                if (array_key_exists($segment, $value->getAttributes())
                    || $value->hasGetMutator($segment)
                    || $value->hasAttributeMutator($segment)
                ) {
                    $value = $value->getAttribute($segment);
                    continue;
                }

                if (method_exists($value, $segment)) {
                    $value = $value->{$segment};
                    continue;
                }

                $value = $value->getAttribute($segment);
                continue;
            }

            if (is_array($value) && array_key_exists($segment, $value)) {
                $value = $value[$segment];
                continue;
            }

            return null;
        }

        return $value;
    }

    /**
     * Separator used when concatenating results from `*`. Schemas can override
     * if they want a different convention (eg. ", " for inline lists).
     */
    protected function multiSeparator(): string
    {
        return ' | ';
    }

    /**
     * The default unique key used when the import job does not specify one.
     * Schemas should override this when they have a natural unique column
     * (e.g. 'document_number' for partner, 'name' for category).
     */
    public function defaultUniqueKey(): ?string
    {
        return null;
    }

    /**
     * Generic validation built from FieldDefinition metadata. Schemas with
     * cross-field rules can override or post-process.
     *
     * @param  array<string, mixed>  $row
     * @return array<int, string>
     */
    public function validateRow(array $row, string $mode): array
    {
        $rules = [];
        foreach ($this->fields() as $field) {
            if (! $field->importable) {
                continue;
            }
            $rules[$field->key] = $this->ruleForField($field, $mode);
        }

        $validator = Validator::make($row, $rules);
        if ($validator->fails()) {
            return array_map('strval', $validator->errors()->all());
        }
        return [];
    }

    /**
     * Build Laravel validation rules for a single field. Override per-schema
     * to add cross-field or contextual rules.
     *
     * @return array<int, mixed>
     */
    protected function ruleForField(FieldDefinition $field, string $mode): array
    {
        $rules = [];

        // Required only enforced when creating; on update/upsert a missing
        // column is treated as "do not change" rather than a hard error.
        if ($field->required && in_array($mode, ['create', 'upsert'], true)) {
            $rules[] = 'required';
        } else {
            $rules[] = 'nullable';
        }

        // Skip type checks for FK columns: their value can be id OR a label
        // string, which is fine — it's resolved later in persistRow().
        if ($field->relationResource !== null) {
            return $rules;
        }

        switch ($field->type) {
            case 'integer':
                $rules[] = 'integer';
                break;
            case 'decimal':
                $rules[] = 'numeric';
                break;
            case 'date':
            case 'datetime':
                $rules[] = 'date';
                break;
            case 'enum':
                if (! empty($field->enumValues)) {
                    $rules[] = Rule::in($field->enumValues);
                }
                break;
            case 'boolean':
                // Accept many CSV-friendly representations; cast handles it.
                break;
            case 'string':
            default:
                // No length cap by default — schemas can override.
                break;
        }

        return $rules;
    }

    /**
     * Generic create / update / upsert. Casts values, resolves FKs, and
     * delegates persistence to Eloquent so model events (mutators, observers,
     * boot hooks) keep working.
     *
     * @param  array<string, mixed>  $row
     */
    public function persistRow(array $row, string $mode, ?string $uniqueKey = null): Model
    {
        $attrs = $this->prepareAttributes($row);

        $key = $uniqueKey ?: $this->defaultUniqueKey() ?: 'id';
        $modelClass = $this->modelClass();

        if ($mode === 'create') {
            return $modelClass::query()->create($attrs);
        }

        if (empty($attrs[$key])) {
            throw new \RuntimeException("Cannot {$mode} without a value for unique key [{$key}].");
        }

        $existing = $modelClass::query()->where($key, $attrs[$key])->first();

        if ($mode === 'update') {
            if (! $existing) {
                throw new \RuntimeException("No record found with {$key}=[{$attrs[$key]}].");
            }
            $existing->fill($attrs)->save();
            return $existing;
        }

        // upsert
        if ($existing) {
            $existing->fill($attrs)->save();
            return $existing;
        }
        return $modelClass::query()->create($attrs);
    }

    /**
     * Resolve relations + cast scalars + drop empty strings. Returns the
     * attributes array ready for Eloquent fill().
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function prepareAttributes(array $row): array
    {
        $attrs = [];

        foreach ($this->fields() as $field) {
            if (! $field->importable) {
                continue;
            }
            if (! array_key_exists($field->key, $row)) {
                continue;
            }
            $value = $row[$field->key];
            if ($value === '') {
                $value = null;
            }

            if ($field->relationResource !== null && $value !== null) {
                $value = $this->resolveRelation(
                    $field->relationResource,
                    $value,
                    $field->relationMatchBy ?? ['id', 'name'],
                );
                if ($value === null) {
                    throw new \RuntimeException(
                        "Could not resolve [{$field->label}] = [{$row[$field->key]}] in {$field->relationResource}."
                    );
                }
            } else {
                $value = $this->castValue($value, $field->type);
            }

            $attrs[$field->key] = $value;
        }

        return $attrs;
    }

    protected function castValue(mixed $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        switch ($type) {
            case 'integer':
                return (int) $value;
            case 'decimal':
                return (float) $value;
            case 'boolean':
                if (is_bool($value)) {
                    return $value;
                }
                $str = is_string($value) ? mb_strtolower(trim($value)) : $value;
                if (in_array($str, ['1', 'true', 'yes', 'sí', 'si', 'y', 't'], true)) {
                    return true;
                }
                if (in_array($str, ['0', 'false', 'no', 'n', 'f', ''], true)) {
                    return false;
                }
                return (bool) $value;
            case 'date':
            case 'datetime':
                return is_string($value) ? trim($value) : $value;
            case 'string':
            case 'enum':
            default:
                return is_string($value) ? trim($value) : $value;
        }
    }

    /**
     * Look up a record in the related resource. Tries each column in
     * `$matchBy` in order and returns the matched id, or null if none hit.
     *
     * @param  array<int, string>  $matchBy
     */
    protected function resolveRelation(string $resource, mixed $value, array $matchBy): ?int
    {
        $value = is_string($value) ? trim($value) : $value;
        if ($value === null || $value === '') {
            return null;
        }

        $registry = app(ImportExportRegistry::class);
        if (! $registry->has($resource)) {
            return null;
        }

        $modelClass = $registry->get($resource)->modelClass();

        foreach ($matchBy as $column) {
            $candidate = $value;
            if ($column === 'id') {
                if (! is_numeric($candidate)) {
                    continue;
                }
                $candidate = (int) $candidate;
            }
            $hit = $modelClass::query()->where($column, $candidate)->value('id');
            if ($hit !== null) {
                return (int) $hit;
            }
        }

        return null;
    }

    /**
     * Eloquent class backing this schema. Used by the generic persistRow()
     * and as the resolution target when this schema appears as another
     * field's `relationResource`.
     *
     * @return class-string<Model>
     */
    public function modelClass(): string
    {
        /** @var Model $model */
        $model = $this->query()->getModel();
        return $model::class;
    }

    protected function normalizeScalar(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return $value;
    }
}
