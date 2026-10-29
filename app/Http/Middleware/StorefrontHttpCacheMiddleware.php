<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StorefrontHttpCacheMiddleware
{
    /**
     * Cache public storefront GET responses using ETags and short stale-while-revalidate TTL.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only cache successful public GET requests
        if (! $request->isMethod('GET') || $response->getStatusCode() !== 200) {
            return $response;
        }

        // Do not cache authenticated sessions (merchant, affiliate, staff, customer)
        if ($request->user() || auth('affiliate')->check() || auth('customer')->check()) {
            $response->headers->set('Cache-Control', 'no-cache, private');

            return $response;
        }

        $content = $response->getContent();
        if ($content !== false && $content !== '') {
            $etag = '"'.md5($content).'"';
            $response->setEtag($etag);

            if ($request->getETags() && in_array($etag, $request->getETags(), true)) {
                $response->setNotModified();

                return $response;
            }
        }

        // 60 seconds browser cache with 30 seconds stale-while-revalidate
        $response->headers->set('Cache-Control', 'public, max-age=60, stale-while-revalidate=30');

        return $response;
    }
}
