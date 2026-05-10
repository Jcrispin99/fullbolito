<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Reads the X-Company-Ids header and makes validated IDs
 * available via $request->get('_company_ids').
 *
 * An empty header (or missing) means "all companies" — no filter applied.
 */
final class ParseCompanyFilter
{
    public function handle(Request $request, Closure $next): mixed
    {
        $raw = $request->header('X-Company-Ids', '');

        if ($raw !== '' && $raw !== null) {
            $ids = array_filter(
                array_map('intval', explode(',', $raw)),
                fn (int $id) => $id > 0,
            );

            if (count($ids) > 0) {
                $request->attributes->set('_company_ids', $ids);
            }
        }

        return $next($request);
    }
}
