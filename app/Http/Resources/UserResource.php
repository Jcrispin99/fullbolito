<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
final class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $tenants = $this->whenLoaded('tenants', function () {
            return $this->tenants
                ->map(fn($t) => [
                    'id' => $t->id,
                    'business_name' => $t->getAttribute('business_name'),
                ])
                ->values();
        });

        $currentTenant = function_exists('tenant') ? tenant() : null;

        $companies = $this->whenLoaded('companies', function () {
            return $this->companies
                ->map(fn ($c) => [
                    'id' => $c->id,
                    'business_name' => $c->business_name,
                ])
                ->values();
        });

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'roles' => $this->getRoleNames()->all(),
            'permissions' => $this->getAllPermissions()->pluck('name')->all(),
            'companies' => $companies,
            'tenants' => $tenants,
            'plan' => $currentTenant?->getPlanSlug(),
            'features' => $currentTenant ? $currentTenant->getFeatures() : [],
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
