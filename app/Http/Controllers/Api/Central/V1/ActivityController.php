<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Central\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\ActivityResource;
use Illuminate\Http\JsonResponse;
use Spatie\Activitylog\Models\Activity;
use App\Models\Tenant;

final class ActivityController extends ApiController
{
    public function index(): JsonResponse
    {
        $subjectKey = (string) request()->input('subject', '');
        $subjectId = request()->input('subject_id');

        if ($subjectKey === '' || $subjectId === null) {
            return $this->validationError([
                'subject' => ['The subject field is required.'],
                'subject_id' => ['The subject_id field is required.'],
            ]);
        }

        $subjectClass = config("activity_subjects.{$subjectKey}");
        if (! is_string($subjectClass) || ! class_exists($subjectClass)) {
            return $this->validationError([
                'subject' => ['The selected subject is invalid.'],
            ]);
        }

        $perPage = (int) request()->input('per_page', 20);
        $perPage = max(1, min(100, $perPage));

        if ($subjectClass === Tenant::class) {
            $tenant = Tenant::query()->find($subjectId);
            if (! $tenant) {
                return $this->notFound('Subject not found.');
            }

            $query = Activity::query()
                ->where('log_name', 'tenants')
                ->where('properties->tenant_id', (string) $subjectId)
                ->with('causer')
                ->latest();
        } else {
            $subject = $subjectClass::query()->find($subjectId);
            if (! $subject) {
                return $this->notFound('Subject not found.');
            }

            $query = Activity::query()
                ->forSubject($subject)
                ->with('causer')
                ->latest();
        }

        $logName = request()->input('log_name');
        if (is_string($logName) && $logName !== '') {
            $query->where('log_name', $logName);
        }

        $event = request()->input('event');
        if (is_string($event) && $event !== '') {
            $query->where('event', $event);
        }

        $activities = $query->paginate($perPage);

        return $this->success(
            ActivityResource::collection($activities)->response()->getData(true)
        );
    }
}
