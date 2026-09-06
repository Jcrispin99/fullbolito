<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureTenantBillingManager
{
    /**
     * Only the tenant owner or an administrator may create, change or
     * cancel charges. Read-only billing routes retain normal tenant access.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        /** @var Tenant|null $tenant */
        $tenant = tenant();

        if (! $user instanceof User || ! $tenant) {
            abort(403, 'No tienes permiso para administrar la facturación.');
        }

        $ownerEmail = $tenant->getAttribute('owner_email');
        $isOwner = is_string($ownerEmail)
            && $ownerEmail !== ''
            && mb_strtolower($ownerEmail) === mb_strtolower($user->email);

        if (! $isOwner && ! $user->hasRole('admin')) {
            abort(403, 'No tienes permiso para administrar la facturación.');
        }

        return $next($request);
    }
}
