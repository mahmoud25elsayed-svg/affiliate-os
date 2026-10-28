<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Billing\Exceptions\FeatureNotAvailableException;
use App\Support\Billing\Features;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFeatureEnabled
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $tenant = Features::resolveTenant();

        // If tenant has an active subscription, ensure the feature is included
        if ($tenant && $tenant->subscription) {
            if (! Features::enabled($feature, $tenant)) {
                throw new FeatureNotAvailableException($feature);
            }
        }

        return $next($request);
    }
}
