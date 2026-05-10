<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

final class SiteAssetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'original_name' => $this->original_name,
            'url' => $this->getTenantAssetUrl(),
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'width' => $this->width,
            'height' => $this->height,
            'dominant_color' => $this->dominant_color,
            'alt_text' => $this->alt_text,
            'variants' => $this->variants,
            'folder' => $this->folder,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    private function getTenantAssetUrl(): string
    {
        $tenantId = tenant('id');

        if ($tenantId) {
            return url("storage/{$tenantId}/{$this->path}");
        }

        return Storage::disk('public')->url($this->path);
    }
}
