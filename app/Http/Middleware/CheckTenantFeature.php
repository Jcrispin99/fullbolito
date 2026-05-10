<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Module;
use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks the request when the active tenant's plan (or addons) does not
 * include the requested feature. Usage in routes:
 *
 *   Route::middleware('tenant.feature:sales')->group(...)
 */
final class CheckTenantFeature
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        /** @var Tenant|null $tenant */
        $tenant = tenant();

        if (! $tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant context required.',
                'error_code' => 'TENANT_REQUIRED',
            ], 403);
        }

        if ($tenant->hasFeature($feature)) {
            return $next($request);
        }

        $label = Module::query()->where('key', $feature)->value('label') ?? $feature;

        return response()->json([
            'success' => false,
            'message' => "El módulo «{$label}» no está activo en tu plan.",
            'error_code' => 'MODULE_NOT_ACTIVE',
            'module' => $feature,
        ], 403);
    }
}
