<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\BillingCredential;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BillingCredential
 */
class BillingCredentialResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Los secretos (sol_pass, client_secret) nunca se exponen en respuestas.
        // La UI los trata como write-only: en edición el usuario los deja vacíos
        // para mantener los actuales o los reemplaza explícitamente.
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sol_user' => $this->sol_user,
            'cert_path' => $this->cert_path,
            'cert_filename' => $this->cert_path ? basename($this->cert_path) : null,
            'client_id' => $this->client_id,
            'production' => (bool) $this->production,
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
