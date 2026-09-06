<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\CourtSchedule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CourtSchedule
 */
final class CourtScheduleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'court_id' => $this->court_id,
            'court' => $this->whenLoaded('court', fn () => [
                'id' => $this->court->id,
                'name' => $this->court->name,
                'sport' => $this->court->sport,
            ]),

            'day_of_week' => $this->day_of_week,
            'start_time' => $this->formatTime($this->start_time),
            'end_time' => $this->formatTime($this->end_time),
            'price' => $this->price,
            'slot_duration_minutes' => $this->slot_duration_minutes,
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function formatTime(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        // El driver devuelve "HH:MM:SS"; normalizamos a "HH:MM" para el front.
        return substr($value, 0, 5);
    }
}
