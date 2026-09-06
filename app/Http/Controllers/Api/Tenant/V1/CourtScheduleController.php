<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\Tenant\V1\CourtScheduleRequest;
use App\Http\Resources\CourtScheduleResource;
use App\Models\Court;
use App\Models\CourtSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class CourtScheduleController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 50);
        $courtId = $request->input('court_id');
        $dayOfWeek = $request->input('day_of_week');
        $status = $request->input('status');

        $query = CourtSchedule::query()
            ->with('court')
            ->whereHas('court', fn ($q) => $q->companyFiltered());

        if ($courtId) {
            $query->where('court_id', $courtId);
        }

        if ($dayOfWeek !== null && $dayOfWeek !== '') {
            $query->where('day_of_week', (int) $dayOfWeek);
        }

        if ($status === 'inactive' || $status === 'archived') {
            $query->where('is_active', false);
        } elseif ($status === 'all') {
            // Include both
        } else {
            $query->where('is_active', true);
        }

        $schedules = $query
            ->orderBy('court_id')
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->paginate((int) $perPage)
            ->appends($request->query());

        return $this->success(
            CourtScheduleResource::collection($schedules)->response()->getData(true)
        );
    }

    public function formOptions(): JsonResponse
    {
        $courts = Court::query()
            ->companyFiltered()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'sport', 'slot_duration_minutes']);

        return $this->success([
            'courts' => $courts,
            'days_of_week' => [
                ['value' => 0, 'label' => 'Domingo'],
                ['value' => 1, 'label' => 'Lunes'],
                ['value' => 2, 'label' => 'Martes'],
                ['value' => 3, 'label' => 'Miércoles'],
                ['value' => 4, 'label' => 'Jueves'],
                ['value' => 5, 'label' => 'Viernes'],
                ['value' => 6, 'label' => 'Sábado'],
            ],
        ], 'Form options retrieved successfully');
    }

    public function store(CourtScheduleRequest $request): JsonResponse
    {
        $data = $request->validated();
        $days = $data['day_of_week'];
        unset($data['day_of_week']);

        $schedules = DB::transaction(function () use ($days, $data): array {
            $created = [];
            foreach ($days as $day) {
                $created[] = CourtSchedule::create(array_merge($data, [
                    'day_of_week' => (int) $day,
                ]));
            }

            return $created;
        });

        $collection = collect($schedules)->each(fn ($s) => $s->load('court'));

        return $this->created(CourtScheduleResource::collection($collection));
    }

    public function show(CourtSchedule $courtSchedule): JsonResponse
    {
        $courtSchedule->load('court');

        return $this->success(new CourtScheduleResource($courtSchedule));
    }

    public function update(CourtScheduleRequest $request, CourtSchedule $courtSchedule): JsonResponse
    {
        $courtSchedule->update($request->validated());
        $courtSchedule->load('court');

        return $this->success(new CourtScheduleResource($courtSchedule));
    }

    public function destroy(CourtSchedule $courtSchedule): JsonResponse
    {
        $courtSchedule->delete();

        return $this->noContent();
    }

    public function batchDestroy(Request $request): JsonResponse
    {
        $request->validate(['ids' => 'required|array', 'ids.*' => 'integer']);
        CourtSchedule::whereIn('id', $request->ids)->delete();

        return $this->noContent();
    }

    public function toggleStatus(CourtSchedule $courtSchedule): JsonResponse
    {
        $courtSchedule->update(['is_active' => ! $courtSchedule->is_active]);
        $courtSchedule->load('court');

        return $this->success(new CourtScheduleResource($courtSchedule));
    }
}
