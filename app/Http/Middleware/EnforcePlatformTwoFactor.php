<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforcePlatformTwoFactor
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user && $user->isPlatformAdmin()) {
            // Allow 2FA setup and challenge routes through
            if ($request->routeIs('platform.2fa.*')) {
                return $next($request);
            }

            if (! $user->hasTwoFactorEnabled()) {
                return redirect()->route('platform.2fa.setup');
            }

            if (! session()->get('two_factor_authenticated', false)) {
                return redirect()->route('platform.2fa.challenge');
            }
        }

        return $next($request);
    }
}
