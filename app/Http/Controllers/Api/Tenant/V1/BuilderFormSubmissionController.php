<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\SiteFormSubmissionResource;
use App\Models\Site;
use App\Models\SiteFormSubmission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class BuilderFormSubmissionController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $site = Site::query()->first();

        if (! $site) {
            return $this->notFound('No se encontró el sitio.');
        }

        $perPage = $request->input('per_page', 20);
        $status = $request->input('status');
        $formType = $request->input('form_type');

        $query = $site->formSubmissions()
            ->with('page')
            ->orderByDesc('created_at');

        if ($status && in_array($status, ['new', 'read', 'archived'])) {
            $query->where('status', $status);
        }

        if ($formType) {
            $query->ofType($formType);
        }

        $submissions = $query->paginate((int) $perPage);

        return $this->success(
            SiteFormSubmissionResource::collection($submissions)->response()->getData(true)
        );
    }

    public function show(SiteFormSubmission $submission): JsonResponse
    {
        $submission->load('page');

        if ($submission->status === 'new') {
            $submission->markAsRead();
        }

        return $this->success(new SiteFormSubmissionResource($submission));
    }

    public function destroy(SiteFormSubmission $submission): JsonResponse
    {
        $submission->delete();

        return $this->noContent();
    }

    public function batchDestroy(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        SiteFormSubmission::whereIn('id', $request->input('ids'))->delete();

        return $this->noContent();
    }

    public function markAsRead(SiteFormSubmission $submission): JsonResponse
    {
        $submission->markAsRead();

        return $this->success(new SiteFormSubmissionResource($submission));
    }

    public function archive(SiteFormSubmission $submission): JsonResponse
    {
        $submission->update(['status' => 'archived']);

        return $this->success(new SiteFormSubmissionResource($submission));
    }

    public function stats(): JsonResponse
    {
        $site = Site::query()->first();

        if (! $site) {
            return $this->notFound('No se encontró el sitio.');
        }

        return $this->success([
            'total' => $site->formSubmissions()->count(),
            'new' => $site->formSubmissions()->new()->count(),
            'read' => $site->formSubmissions()->read()->count(),
            'archived' => $site->formSubmissions()->archived()->count(),
        ]);
    }

    public function export(Request $request): JsonResponse
    {
        $site = Site::query()->first();

        if (! $site) {
            return $this->notFound('No se encontró el sitio.');
        }

        $formType = $request->input('form_type');
        $query = $site->formSubmissions()->orderByDesc('created_at');

        if ($formType) {
            $query->ofType($formType);
        }

        $submissions = $query->get();

        $rows = $submissions->map(fn (SiteFormSubmission $s) => [
            'id' => $s->id,
            'form_type' => $s->form_type,
            'status' => $s->status,
            'data' => $s->data,
            'ip_address' => $s->ip_address,
            'created_at' => $s->created_at?->toIso8601String(),
        ]);

        return $this->success($rows);
    }
}
