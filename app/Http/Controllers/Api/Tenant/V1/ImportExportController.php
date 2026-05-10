<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant\V1;

use App\Http\Controllers\Api\ApiController;
use App\ImportExport\ImportExportRegistry;
use App\ImportExport\Runners\ExportRunner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ImportExportController extends ApiController
{
    public function __construct(
        private readonly ImportExportRegistry $registry,
        private readonly ExportRunner $exportRunner,
    ) {
    }

    public function schemas(): JsonResponse
    {
        $schemas = [];
        foreach ($this->registry->all() as $schema) {
            $schemas[] = [
                'key' => $schema->key(),
                'label' => $schema->label(),
                'supports_import' => $schema->supportsImport(),
            ];
        }

        return $this->success($schemas);
    }

    public function schema(string $resource): JsonResponse
    {
        if (! $this->registry->has($resource)) {
            return $this->notFound("Resource [{$resource}] is not registered for import/export.");
        }

        $schema = $this->registry->get($resource);

        return $this->success([
            'key' => $schema->key(),
            'label' => $schema->label(),
            'supports_import' => $schema->supportsImport(),
            'fields' => array_map(
                fn ($field) => $field->toArray(),
                $schema->fields(),
            ),
        ]);
    }

    public function export(Request $request): JsonResponse|StreamedResponse
    {
        $validated = $request->validate([
            'resource' => ['required', 'string'],
            'columns' => ['nullable', 'array'],
            'columns.*' => ['string'],
            'filters' => ['nullable', 'array'],
            'filename' => ['nullable', 'string', 'max:120'],
            'format' => ['nullable', 'string', 'in:csv,xlsx'],
            'delimiter' => ['nullable', 'string', 'size:1'],
        ]);

        $resource = $validated['resource'];

        if (! $this->registry->has($resource)) {
            return $this->notFound("Resource [{$resource}] is not registered for import/export.");
        }

        return $this->exportRunner->stream(
            schema: $this->registry->get($resource),
            columns: $validated['columns'] ?? [],
            filters: $validated['filters'] ?? [],
            filename: $validated['filename'] ?? null,
            format: $validated['format'] ?? 'csv',
            delimiter: $validated['delimiter'] ?? ',',
        );
    }
}
