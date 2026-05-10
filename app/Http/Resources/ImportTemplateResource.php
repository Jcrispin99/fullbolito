<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\ImportExport\ImportTemplate
 */
final class ImportTemplateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $userId = $request->user()?->id;
        $template = $this->resource;

        return [
            'id' => $template->id,
            'name' => $template->name,
            'resource' => $template->getAttribute('resource'),
            'direction' => $template->direction,
            'columns' => $template->columns ?? [],
            'default_options' => $template->default_options,
            'owner_user_id' => $template->owner_user_id,
            'owner_name' => $template->relationLoaded('owner') ? $template->owner?->name : null,
            'is_shared' => (bool) $template->is_shared,
            'is_mine' => $userId !== null && $template->owner_user_id === $userId,
            'can_delete' => $userId !== null && $template->owner_user_id === $userId,
            'created_at' => $template->created_at?->toIso8601String(),
            'updated_at' => $template->updated_at?->toIso8601String(),
        ];
    }
}
