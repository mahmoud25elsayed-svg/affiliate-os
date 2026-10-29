<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeadersMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Strip server execution runtime signature (Fix #17)
        @header_remove('X-Powered-By');
        $response->headers->remove('X-Powered-By');

        $isEmbedRoute = $request->is('embed/*') || str_starts_with((string) $request->route()?->getName(), 'storefront.embed.');

        // 1. Anti-Clickjacking: X-Frame-Options
        if (! $isEmbedRoute) {
            $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        }

        // 2. Prevent MIME-type sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // 3. Legacy XSS Filter protection
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // 4. Referrer Policy
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // 5. Restrict sensitive hardware APIs
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        // 6. HTTP Strict Transport Security (HSTS)
        if ($request->isSecure() || app()->environment('production')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        // 7. Content Security Policy (CSP - SEC-15)
        $frameAncestors = $isEmbedRoute ? '*' : "'self'";
        $isFilamentPanel = $request->is('store/admin*') || $request->is('platform*');
        $scriptSrc = $isFilamentPanel ? "'self' 'unsafe-inline' 'unsafe-eval' https:" : "'self' 'unsafe-inline' https:";

        $cspPolicy = implode('; ', [
            "default-src 'self'",
            "script-src {$scriptSrc}",
            "style-src 'self' 'unsafe-inline' https:",
            "img-src 'self' data: https: blob:",
            "font-src 'self' data: https:",
            "connect-src 'self' https:",
            "frame-ancestors {$frameAncestors}",
            "object-src 'none'",
            "base-uri 'self'",
        ]);

        $response->headers->set('Content-Security-Policy', $cspPolicy);

        return $response;
    }
}
