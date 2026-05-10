<?php

declare(strict_types=1);

namespace App\ImportExport;

use App\ImportExport\Contracts\ResourceSchema;
use InvalidArgumentException;

final class ImportExportRegistry
{
    /** @var array<string, ResourceSchema> */
    private array $schemas = [];

    public function register(ResourceSchema $schema): void
    {
        $this->schemas[$schema->key()] = $schema;
    }

    public function has(string $key): bool
    {
        return isset($this->schemas[$key]);
    }

    public function get(string $key): ResourceSchema
    {
        if (! isset($this->schemas[$key])) {
            throw new InvalidArgumentException("ImportExport resource [{$key}] is not registered.");
        }

        return $this->schemas[$key];
    }

    /**
     * @return array<string, ResourceSchema>
     */
    public function all(): array
    {
        return $this->schemas;
    }
}
