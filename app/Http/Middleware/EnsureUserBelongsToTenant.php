<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserBelongsToTenant
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->guest(route('login'));
        }

        // Platform admins have cross-tenant access rights
        if ($user->is_platform_admin) {
            return $next($request);
        }

        $tenantId = function_exists('tenant') && tenant('id') ? (string) tenant('id') : null;

        if ($tenantId) {
            if ($user->tenant_id === null) {
                $user->updateQuietly(['tenant_id' => $tenantId]);
                $user->tenant_id = $tenantId;
            } elseif ($user->tenant_id !== $tenantId) {
                abort(403, 'غير مصرح لك بالوصول إلى بيانات أو لوحة تحكم هذا المتجر.');
            }
        }

        return $next($request);
    }
}
