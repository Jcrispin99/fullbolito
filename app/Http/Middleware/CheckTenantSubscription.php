<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class CheckTenantSubscription
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // An expired customer must retain access to self-service billing so
        // they can review charges and start a new paid subscription. These
        // endpoints still enforce auth:sanctum and billing.manage themselves.
        if ($request->is('api/v1/billing', 'api/v1/billing/*')) {
            return $next($request);
        }

        // 1. Obtener el tenant actual
        /** @var \App\Models\Tenant|null $tenant */
        $tenant = tenant();

        if (! $tenant) {
            // Si no estamos en contexto tenant, dejamos pasar (no aplica)
            return $next($request);
        }

        // 2. Cargar la suscripción si no está cargada
        if (! $tenant->relationLoaded('subscription')) {
            $tenant->load('subscription');
        }

        /** @var \App\Models\Subscription|null $subscription */
        $subscription = $tenant->subscription;

        // 3. Validar suscripción
        if (! $subscription || ! $subscription->isValid()) {
            return response()->json([
                'message' => 'Subscription expired or inactive. Please upgrade your plan to continue access.',
                'error_code' => 'SUBSCRIPTION_EXPIRED',
            ], 403);
        }

        return $next($request);
    }
}
