<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStorefrontActive
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Tenant|null $tenant */
        $tenant = null;

        if (function_exists('tenancy') && tenancy()->initialized) {
            /** @var Tenant|null $tenant */
            $tenant = tenancy()->tenant;
        } elseif (function_exists('tenant') && tenant('id')) {
            /** @var Tenant|null $tenant */
            $tenant = Tenant::find(tenant('id'));
        }

        if ($tenant) {
            $tenant = $tenant->fresh() ?? $tenant;
        }

        if ($tenant) {
            if ($tenant->isSuspended()) {
                return response()->view('storefront.suspended', [
                    'tenantName' => $tenant->name,
                ], 503);
            }

            // BL-06: Storefront must be suspended if subscription is no longer usable (e.g. trial ended)
            $subscription = $tenant->subscription;
            if ($subscription && ! $subscription->isUsable()) {
                return response()->view('storefront.suspended', [
                    'tenantName' => $tenant->name,
                ], 503);
            }
        }

        return $next($request);
    }
}
