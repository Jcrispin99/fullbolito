<?php

declare(strict_types=1);

namespace App\ImportExport\Support;

use App\ImportExport\Contracts\ResourceSchema;

final class AutoMapper
{
    /**
     * Suggest a mapping from file headers to schema field keys.
     *
     * Returns a map of fileHeader => fieldKey|null. Tries to match by:
     *  - exact normalized label
     *  - exact normalized key
     *  - partial contains
     *
     * @param  array<int, string>  $headers
     * @return array<string, string|null>
     */
    public function suggest(ResourceSchema $schema, array $headers): array
    {
        $importableFields = array_filter(
            $schema->fields(),
            static fn ($f) => $f->importable,
        );

        $byNormalizedLabel = [];
        $byNormalizedKey = [];
        foreach ($importableFields as $field) {
            $byNormalizedLabel[$this->normalize($field->label)] = $field->key;
            $byNormalizedKey[$this->normalize($field->key)] = $field->key;
        }

        $mapping = [];
        $usedKeys = [];

        foreach ($headers as $header) {
            $norm = $this->normalize($header);

            $matched = $byNormalizedLabel[$norm]
                ?? $byNormalizedKey[$norm]
                ?? null;

            if ($matched === null) {
                foreach ($byNormalizedLabel as $candidate => $fieldKey) {
                    if ($norm !== '' && (str_contains($candidate, $norm) || str_contains($norm, $candidate))) {
                        $matched = $fieldKey;
                        break;
                    }
                }
            }

            if ($matched !== null && in_array($matched, $usedKeys, true)) {
                $matched = null;
            }

            if ($matched !== null) {
                $usedKeys[] = $matched;
            }

            $mapping[$header] = $matched;
        }

        return $mapping;
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = (string) preg_replace('/[áàäâã]/u', 'a', $value);
        $value = (string) preg_replace('/[éèëê]/u', 'e', $value);
        $value = (string) preg_replace('/[íìïî]/u', 'i', $value);
        $value = (string) preg_replace('/[óòöôõ]/u', 'o', $value);
        $value = (string) preg_replace('/[úùüû]/u', 'u', $value);
        $value = (string) preg_replace('/ñ/u', 'n', $value);
        $value = (string) preg_replace('/[^a-z0-9]+/u', '_', $value);
        return trim($value, '_');
    }
}
