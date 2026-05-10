<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\ImportTemplateResource;
use App\ImportExport\ImportExportRegistry;
use App\Models\ImportExport\ImportTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ImportExportTemplateController extends ApiController
{
    public function __construct(
        private readonly ImportExportRegistry $registry,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $userId = (int) $request->user()->id;

        $query = ImportTemplate::query()
            ->with('owner:id,name,email')
            ->where(function ($q) use ($userId): void {
                $q->where('owner_user_id', $userId)
                    ->orWhere('is_shared', true);
            });

        if ($resource = $request->query('resource')) {
            $query->where('resource', $resource);
        }

        if ($direction = $request->query('direction')) {
            $query->where('direction', $direction);
        }

        $templates = $query->orderBy('name')->get();

        return $this->success(ImportTemplateResource::collection($templates));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'resource' => ['required', 'string', 'max:60'],
            'direction' => ['required', 'string', 'in:import,export'],
            'columns' => ['required', 'array', 'min:1'],
            'columns.*' => ['string'],
            'default_options' => ['nullable', 'array'],
            'is_shared' => ['nullable', 'boolean'],
        ]);

        if (! $this->registry->has($validated['resource'])) {
            return $this->error("Resource [{$validated['resource']}] is not registered.", 422);
        }

        $template = ImportTemplate::create([
            'name' => $validated['name'],
            'resource' => $validated['resource'],
            'direction' => $validated['direction'],
            'columns' => $validated['columns'],
            'default_options' => $validated['default_options'] ?? null,
            'owner_user_id' => $request->user()->id,
            'is_shared' => (bool) ($validated['is_shared'] ?? false),
        ]);

        return $this->created(new ImportTemplateResource($template->load('owner:id,name,email')));
    }

    public function update(Request $request, ImportTemplate $template): JsonResponse
    {
        if ($template->owner_user_id !== $request->user()->id) {
            return $this->forbidden('You can only update your own templates.');
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'columns' => ['sometimes', 'array', 'min:1'],
            'columns.*' => ['string'],
            'default_options' => ['nullable', 'array'],
            'is_shared' => ['sometimes', 'boolean'],
        ]);

        $template->update($validated);

        return $this->success(new ImportTemplateResource($template->load('owner:id,name,email')));
    }

    public function destroy(Request $request, ImportTemplate $template): JsonResponse
    {
        if ($template->owner_user_id !== $request->user()->id) {
            return $this->forbidden('You can only delete your own templates.');
        }

        $template->delete();

        return $this->noContent();
    }
}
