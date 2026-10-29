<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hides the stock Lunar admin hub (/lunar) unless LUNAR_ADMIN_PANEL is enabled.
 */
class AbortUnlessLunarHubEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless((bool) config('app.lunar_admin_panel'), 404);

        return $next($request);
    }
}
