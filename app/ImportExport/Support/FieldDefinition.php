<?php

declare(strict_types=1);

namespace App\ImportExport\Support;

final class FieldDefinition
{
    /**
     * @param  array<string, mixed>|null  $enumValues
     * @param  array<int, string>|null  $relationMatchBy  Order of columns the import engine
     *         tries when resolving a foreign-key value to an actual record. For example
     *         ['id', 'name'] means: if the imported value looks numeric try id first,
     *         otherwise try name. Only meaningful when relationResource is set.
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $type = 'string',
        public readonly bool $required = false,
        public readonly bool $unique = false,
        public readonly bool $exportable = true,
        public readonly bool $importable = true,
        public readonly ?string $relationResource = null,
        public readonly ?array $enumValues = null,
        public readonly ?string $help = null,
        public readonly ?array $relationMatchBy = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'type' => $this->type,
            'required' => $this->required,
            'unique' => $this->unique,
            'exportable' => $this->exportable,
            'importable' => $this->importable,
            'relation_resource' => $this->relationResource,
            'enum_values' => $this->enumValues,
            'help' => $this->help,
            'relation_match_by' => $this->relationMatchBy,
        ];
    }
}
