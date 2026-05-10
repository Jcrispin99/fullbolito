<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\ImportJobResource;
use App\ImportExport\ImportExportRegistry;
use App\ImportExport\Runners\ImportRunner;
use App\ImportExport\Support\AutoMapper;
use App\ImportExport\Support\FileAnalyzer;
use App\Models\ImportExport\ImportJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ImportJobController extends ApiController
{
    public function __construct(
        private readonly ImportExportRegistry $registry,
        private readonly FileAnalyzer $analyzer,
        private readonly AutoMapper $autoMapper,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $query = ImportJob::query()
            ->where('user_id', $request->user()->id)
            ->latest();

        if ($resource = $request->query('resource')) {
            $query->where('resource', $resource);
        }

        $jobs = $query->limit(50)->get();

        return $this->success(ImportJobResource::collection($jobs));
    }

    public function show(Request $request, string $uuid): JsonResponse
    {
        $job = ImportJob::where('uuid', $uuid)->firstOrFail();
        $this->ensureOwner($request, $job);

        return $this->success(new ImportJobResource($job));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'resource' => ['required', 'string'],
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:20480'],
        ]);

        $resource = $validated['resource'];
        if (! $this->registry->has($resource)) {
            return $this->error("Resource [{$resource}] is not registered.", 422);
        }

        $schema = $this->registry->get($resource);

        if (! $schema->supportsImport()) {
            return $this->error("Resource [{$resource}] does not support importing.", 422);
        }

        $upload = $request->file('file');
        $extension = strtolower($upload->getClientOriginalExtension() ?: $upload->extension());
        $format = in_array($extension, ['xlsx'], true) ? 'xlsx' : 'csv';

        $uuid = (string) Str::uuid();
        $userId = (int) $request->user()->id;
        $filename = "{$uuid}.{$format}";
        $relativePath = "imports/{$userId}/{$filename}";

        Storage::disk('local')->putFileAs(
            "imports/{$userId}",
            $upload,
            $filename,
        );

        $absolutePath = Storage::disk('local')->path($relativePath);

        $delimiter = $format === 'csv' ? $this->analyzer->detectCsvDelimiter($absolutePath) : null;

        $analysis = $this->analyzer->analyze($absolutePath, $format, $delimiter, previewLimit: 10);
        $headers = $analysis['headers'];
        $preview = $analysis['preview'];
        $totalRows = $analysis['total_rows'];

        $suggested = $this->autoMapper->suggest($schema, $headers);

        $job = ImportJob::create([
            'uuid' => $uuid,
            'resource' => $resource,
            'user_id' => $userId,
            'original_filename' => $upload->getClientOriginalName(),
            'file_path' => $relativePath,
            'file_size' => $upload->getSize(),
            'format' => $format,
            'delimiter' => $delimiter,
            'encoding' => 'utf-8',
            'mapping' => $suggested,
            'mode' => 'create',
            'options' => [
                'stop_on_error' => false,
                'dry_run' => false,
                'chunk_size' => 500,
            ],
            'status' => 'draft',
            'total_rows' => $totalRows,
        ]);

        $job->setAttribute('preview', $preview);
        $job->setAttribute('headers', $headers);
        $job->setAttribute('suggested_mapping', $suggested);

        return $this->created(new ImportJobResource($job));
    }

    public function update(Request $request, string $uuid): JsonResponse
    {
        $job = ImportJob::where('uuid', $uuid)->firstOrFail();
        $this->ensureOwner($request, $job);

        if (! in_array($job->status, ['draft', 'failed', 'partial'], true)) {
            return $this->error('This import is locked because it has already been processed.', 422);
        }

        $validated = $request->validate([
            'mapping' => ['sometimes', 'array'],
            'mode' => ['sometimes', 'string', 'in:create,update,upsert'],
            'unique_key' => ['nullable', 'string', 'max:60'],
            'options' => ['sometimes', 'array'],
            'options.stop_on_error' => ['sometimes', 'boolean'],
            'options.dry_run' => ['sometimes', 'boolean'],
        ]);

        $job->fill($validated);
        $job->save();

        return $this->success(new ImportJobResource($job));
    }

    public function destroy(Request $request, string $uuid): JsonResponse
    {
        $job = ImportJob::where('uuid', $uuid)->firstOrFail();
        $this->ensureOwner($request, $job);

        if (! empty($job->file_path)) {
            Storage::disk('local')->delete($job->file_path);
        }
        if (! empty($job->errors_file_path)) {
            Storage::disk('local')->delete($job->errors_file_path);
        }

        $job->delete();

        return $this->noContent();
    }

    public function run(Request $request, string $uuid, ImportRunner $runner): JsonResponse
    {
        $job = ImportJob::where('uuid', $uuid)->firstOrFail();
        $this->ensureOwner($request, $job);

        if (! in_array($job->status, ['draft', 'failed', 'partial'], true)) {
            return $this->error('This import has already been run.', 422);
        }

        $request->validate([
            'mapping' => ['sometimes', 'array'],
            'mode' => ['sometimes', 'string', 'in:create,update,upsert'],
            'unique_key' => ['nullable', 'string', 'max:60'],
            'options' => ['sometimes', 'array'],
        ]);

        if ($request->has('mapping')) $job->mapping = $request->input('mapping');
        if ($request->has('mode')) $job->mode = $request->input('mode');
        if ($request->has('unique_key')) $job->unique_key = $request->input('unique_key');
        if ($request->has('options')) $job->options = array_merge((array) $job->options, $request->input('options'));
        $job->save();

        $schema = $this->registry->get($job->getAttribute('resource'));

        $runner->run($job, $schema);

        return $this->success(new ImportJobResource($job->fresh()));
    }

    public function downloadErrors(Request $request, string $uuid): StreamedResponse|JsonResponse
    {
        $job = ImportJob::where('uuid', $uuid)->firstOrFail();
        $this->ensureOwner($request, $job);

        if (empty($job->errors_file_path) || ! Storage::disk('local')->exists($job->errors_file_path)) {
            return $this->notFound('No errors file available for this import.');
        }

        return Storage::disk('local')->download(
            $job->errors_file_path,
            "errors-{$job->original_filename}",
        );
    }

    private function ensureOwner(Request $request, ImportJob $job): void
    {
        if ($job->user_id !== $request->user()->id) {
            abort(403, 'You can only access your own import jobs.');
        }
    }
}
