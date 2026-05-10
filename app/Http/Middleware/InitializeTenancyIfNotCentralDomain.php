<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Contracts\TenantCouldNotBeIdentifiedException;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;

final class InitializeTenancyIfNotCentralDomain
{
    public function handle(Request $request, Closure $next): mixed
    {
        $host = mb_strtolower($request->getHost());

        $centralDomains = array_map(
            static fn (string $domain): string => mb_strtolower($domain),
            config('tenancy.central_domains', [])
        );

        if (in_array($host, $centralDomains, true)) {
            return $next($request);
        }

        try {
            return app(InitializeTenancyByDomain::class)->handle($request, $next);
        } catch (TenantCouldNotBeIdentifiedException) {
            abort(404);
        }
    }
}

