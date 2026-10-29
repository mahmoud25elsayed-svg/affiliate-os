<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Analytics\Services\PlatformVisitorAnalyticsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class TrackPlatformVisitorsMiddleware
{
    public function __construct(
        protected PlatformVisitorAnalyticsService $analyticsService
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    /**
     * Perform post-response background logging of visitor metrics.
     */
    public function terminate(Request $request, Response $response): void
    {
        // Only track GET requests that are successful HTML views
        if (! $request->isMethod('GET') || $response->getStatusCode() >= 400 || $request->expectsJson()) {
            return;
        }

        try {
            $this->analyticsService->recordVisit($request);
        } catch (Throwable) {
            // Ignore logging errors to ensure response availability
        }
    }
}
