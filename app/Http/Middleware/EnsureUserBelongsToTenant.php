<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
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

        if (! $user instanceof User) {
            return redirect()->guest(tenant('id') ? url('/store/login') : route('login'));
        }

        // Platform admins have cross-tenant access rights
        if ($user->is_platform_admin) {
            return $next($request);
        }

        $tenantId = function_exists('tenant') && tenant('id') ? (string) tenant('id') : null;

        // A user without a tenant is never adopted by the store they happen to visit:
        // membership is only granted at provisioning or staff invitation time.
        if ($tenantId && (string) $user->tenant_id !== $tenantId) {
            abort(403, __('غير مصرح لك بالوصول إلى بيانات أو لوحة تحكم هذا المتجر.'));
        }

        return $next($request);
    }
}
