<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Domain;
use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectSubdomainToCustomDomain
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! function_exists('tenant') || ! tenant('id')) {
            return $next($request);
        }

        $currentHost = mb_strtolower($request->getHost());

        /** @var Tenant|null $tenant */
        $tenant = Tenant::find(tenant('id'));
        if (! $tenant) {
            return $next($request);
        }

        /** @var Domain|null $currentDomainModel */
        $currentDomainModel = Domain::query()
            ->where('tenant_id', (string) $tenant->id)
            ->where('domain', $currentHost)
            ->first();

        if ($currentDomainModel && $currentDomainModel->redirect_to_primary) {
            /** @var Domain|null $primaryDomain */
            $primaryDomain = Domain::query()
                ->where('tenant_id', (string) $tenant->id)
                ->where('is_primary', true)
                ->where('status', Domain::STATUS_ACTIVE)
                ->first();

            if ($primaryDomain && strcasecmp($primaryDomain->domain, $currentHost) !== 0) {
                $targetScheme = $request->isSecure() ? 'https' : 'http';
                $targetUrl = "{$targetScheme}://{$primaryDomain->domain}".$request->getRequestUri();

                return redirect()->away($targetUrl, 301);
            }
        }

        return $next($request);
    }
}
