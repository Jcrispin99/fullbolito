<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\ActivityResource;
use Illuminate\Http\JsonResponse;
use Spatie\Activitylog\Models\Activity;

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

        if (! is_numeric($subjectId)) {
            return $this->validationError([
                'subject_id' => ['The subject_id must be a number.'],
            ]);
        }

        $subjectClass = config("activity_subjects.{$subjectKey}");
        if (! is_string($subjectClass) || ! class_exists($subjectClass)) {
            return $this->validationError([
                'subject' => ['The selected subject is invalid.'],
            ]);
        }

        $subject = $subjectClass::query()->find((int) $subjectId);
        if (! $subject) {
            return $this->notFound('Subject not found.');
        }

        $perPage = (int) request()->input('per_page', 20);
        $perPage = max(1, min(100, $perPage));

        $query = Activity::query()
            ->forSubject($subject)
            ->with('causer')
            ->latest();

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
