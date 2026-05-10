<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\Activitylog\Models\Activity;

/**
 * @mixin Activity
 */
final class ActivityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $causer = $this->causer;

        return [
            'id' => $this->id,
            'log_name' => $this->log_name,
            'event' => $this->event,
            'description' => $this->description,
            'subject_type' => $this->subject_type,
            'subject_id' => $this->subject_id,
            'causer' => $causer ? [
                'id' => $causer->id,
                'name' => $causer->name ?? null,
                'email' => $causer->email ?? null,
            ] : null,
            'properties' => $this->properties,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

