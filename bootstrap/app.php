<?php

use App\Http\Middleware\EnsureFeatureEnabled;
use App\Http\Middleware\EnsureUserBelongsToTenant;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeadersMiddleware;
use App\Http\Middleware\StorefrontHttpCacheMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SecurityHeadersMiddleware::class,
            HandleInertiaRequests::class,
        ]);

        $middleware->api(append: [
            SecurityHeadersMiddleware::class,
        ]);

        $middleware->alias([
            'feature' => EnsureFeatureEnabled::class,
            'storefront.cache' => StorefrontHttpCacheMiddleware::class,
            'tenant.user' => EnsureUserBelongsToTenant::class,
        ]);

        // Couriers call these server-to-server; they are authenticated by signature, not session CSRF.
        $middleware->validateCsrfTokens(except: ['webhooks/courier/*']);

        // Store staff sign in on their own store domain; only central routes fall back to the platform login.
        $middleware->redirectGuestsTo(fn () => function_exists('tenancy') && tenancy()->initialized
            ? url('/store/login')
            : route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
