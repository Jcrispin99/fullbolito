<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SiteBlockCatalog;

final class BlockSchemaValidator
{
    /** @var array<string, array<string, mixed>> */
    private array $schemaCache = [];

    /**
     * Valida el content y style_overrides de una sección contra el schema de su tipo de bloque.
     *
     * @param  array<string, mixed>  $content
     * @param  array<string, mixed>  $styleOverrides
     * @return array{valid: bool, errors: array<string, string[]>}
     */
    public function validate(string $blockTypeKey, array $content, array $styleOverrides = [], bool $skipRequired = false): array
    {
        $schema = $this->loadSchema($blockTypeKey);

        if ($schema === null) {
            return [
                'valid' => false,
                'errors' => ['block_type_key' => ["El tipo de bloque '{$blockTypeKey}' no existe en el catálogo."]],
            ];
        }

        $errors = [];

        $contentFields = $schema['fields'] ?? [];
        foreach ($contentFields as $fieldName => $fieldSchema) {
            $value = $content[$fieldName] ?? null;
            $fieldErrors = $this->validateField($fieldName, $value, $fieldSchema, $skipRequired);

            if ($fieldErrors !== []) {
                $errors["content.{$fieldName}"] = $fieldErrors;
            }
        }

        $styleFields = $schema['style_fields'] ?? [];
        foreach ($styleOverrides as $fieldName => $value) {
            if (! isset($styleFields[$fieldName])) {
                $errors["style_overrides.{$fieldName}"] = ["El campo de estilo '{$fieldName}' no está definido en el schema."];

                continue;
            }

            $fieldErrors = $this->validateField($fieldName, $value, $styleFields[$fieldName], $skipRequired);

            if ($fieldErrors !== []) {
                $errors["style_overrides.{$fieldName}"] = $fieldErrors;
            }
        }

        return [
            'valid' => $errors === [],
            'errors' => $errors,
        ];
    }

    /**
     * Verifica si un tipo de bloque existe en el catálogo.
     */
    public function blockTypeExists(string $blockTypeKey): bool
    {
        return $this->loadSchema($blockTypeKey) !== null;
    }

    /**
     * Obtiene el schema completo de un tipo de bloque.
     *
     * @return array<string, mixed>|null
     */
    public function getSchema(string $blockTypeKey): ?array
    {
        return $this->loadSchema($blockTypeKey);
    }

    /**
     * Carga el schema desde el catálogo, con cache en memoria.
     *
     * @return array<string, mixed>|null
     */
    private function loadSchema(string $blockTypeKey): ?array
    {
        if (isset($this->schemaCache[$blockTypeKey])) {
            return $this->schemaCache[$blockTypeKey];
        }

        $catalogEntry = SiteBlockCatalog::where('key', $blockTypeKey)->first();

        if ($catalogEntry === null) {
            return null;
        }

        $this->schemaCache[$blockTypeKey] = $catalogEntry->schema;

        return $this->schemaCache[$blockTypeKey];
    }

    /**
     * Valida un campo individual contra su definición en el schema.
     *
     * @param  array<string, mixed>  $fieldSchema
     * @return string[]
     */
    private function validateField(string $fieldName, mixed $value, array $fieldSchema, bool $skipRequired = false): array
    {
        $errors = [];
        $type = $fieldSchema['type'] ?? 'string';
        $required = $fieldSchema['required'] ?? false;
        $label = $fieldSchema['label'] ?? $fieldName;

        if (! $skipRequired && $required && ($value === null || $value === '' || $value === [])) {
            $errors[] = "El campo '{$label}' es obligatorio.";

            return $errors;
        }

        if ($value === null || $value === '') {
            return $errors;
        }

        $typeError = $this->validateType($value, $type, $fieldSchema);
        if ($typeError !== null) {
            $errors[] = $typeError;
        }

        return $errors;
    }

    /**
     * Valida el tipo de dato de un valor contra el tipo esperado.
     *
     * @param  array<string, mixed>  $fieldSchema
     */
    private function validateType(mixed $value, string $type, array $fieldSchema): ?string
    {
        return match ($type) {
            'string', 'rich_text' => is_string($value) ? null : 'Debe ser texto.',
            'number' => $this->validateNumber($value, $fieldSchema),
            'boolean' => is_bool($value) ? null : 'Debe ser verdadero o falso.',
            'enum' => $this->validateEnum($value, $fieldSchema),
            'color' => $this->validateColor($value),
            'url' => $this->validateUrl($value),
            'asset' => $this->validateAsset($value),
            'asset_list' => $this->validateAssetList($value, $fieldSchema),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $fieldSchema
     */
    private function validateNumber(mixed $value, array $fieldSchema): ?string
    {
        if (! is_numeric($value)) {
            return 'Debe ser un número.';
        }

        $numericValue = (float) $value;

        if (isset($fieldSchema['min']) && $numericValue < $fieldSchema['min']) {
            return "Debe ser mayor o igual a {$fieldSchema['min']}.";
        }

        if (isset($fieldSchema['max']) && $numericValue > $fieldSchema['max']) {
            return "Debe ser menor o igual a {$fieldSchema['max']}.";
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $fieldSchema
     */
    private function validateEnum(mixed $value, array $fieldSchema): ?string
    {
        if (! is_string($value)) {
            return 'Debe ser texto.';
        }

        $options = $fieldSchema['options'] ?? [];

        if ($options !== [] && ! in_array($value, $options, true)) {
            $optionList = implode(', ', $options);

            return "Debe ser uno de: {$optionList}.";
        }

        return null;
    }

    private function validateColor(mixed $value): ?string
    {
        if (! is_string($value)) {
            return 'Debe ser un color válido.';
        }

        if (! preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $value)) {
            return 'Debe ser un color hexadecimal válido (ej: #FF0000).';
        }

        return null;
    }

    private function validateUrl(mixed $value): ?string
    {
        if (! is_string($value)) {
            return 'Debe ser una URL válida.';
        }

        if (str_starts_with($value, '/') || str_starts_with($value, '#')) {
            return null;
        }

        if (! filter_var($value, FILTER_VALIDATE_URL)) {
            return 'Debe ser una URL válida.';
        }

        return null;
    }

    private function validateAsset(mixed $value): ?string
    {
        if (! is_array($value)) {
            return 'Debe ser una referencia a un asset.';
        }

        if (! isset($value['asset_id']) && ! isset($value['url'])) {
            return 'Debe contener asset_id o url.';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $fieldSchema
     */
    private function validateAssetList(mixed $value, array $fieldSchema): ?string
    {
        if (! is_array($value)) {
            return 'Debe ser una lista de assets.';
        }

        $max = $fieldSchema['max'] ?? null;
        if ($max !== null && count($value) > $max) {
            return "Máximo {$max} elementos permitidos.";
        }

        foreach ($value as $index => $item) {
            $error = $this->validateAsset($item);
            if ($error !== null) {
                return "Elemento {$index}: {$error}";
            }
        }

        return null;
    }
}
