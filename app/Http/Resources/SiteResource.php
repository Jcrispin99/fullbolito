<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class SiteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'status' => $this->status,
            'custom_domain' => $this->custom_domain,
            'domain_status' => $this->domain_status,
            'domain_verified_at' => $this->domain_verified_at?->toIso8601String(),
            'settings' => $this->settings,
            'theme' => new SiteThemeResource($this->whenLoaded('activeTheme')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
