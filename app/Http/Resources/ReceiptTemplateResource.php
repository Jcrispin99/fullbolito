<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ReceiptTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'layout' => $this->layout,
            'logo_url' => $this->logo_url,
            'updated_at' => $this->updated_at,
        ];
    }
}
