<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class SiteFormSubmissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'site_id' => $this->site_id,
            'page_id' => $this->page_id,
            'section_id' => $this->section_id,
            'form_type' => $this->form_type,
            'data' => $this->data,
            'ip_address' => $this->ip_address,
            'status' => $this->status,
            'read_at' => $this->read_at?->toIso8601String(),
            'page' => new SitePageResource($this->whenLoaded('page')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
