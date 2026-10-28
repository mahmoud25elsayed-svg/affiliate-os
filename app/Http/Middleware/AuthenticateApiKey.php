<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\ApiKey;
use App\Models\Tenant;
use App\Support\Billing\Exceptions\FeatureNotAvailableException;
use App\Support\Billing\Features;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiKey
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var mixed $headerKey */
        $headerKey = $request->header('X-API-Key');
        $rawKey = $request->bearerToken() ?? (is_string($headerKey) ? $headerKey : null);

        if (! is_string($rawKey) || trim($rawKey) === '') {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Missing API Key in Authorization Bearer token or X-API-Key header.',
            ], 401);
        }

        $hash = hash('sha256', trim($rawKey));

        /** @var ApiKey|null $apiKey */
        $apiKey = ApiKey::query()->where('key_hash', $hash)->first();

        if (! $apiKey || ! $apiKey->isValid()) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Invalid or revoked API Key.',
            ], 401);
        }

        // Initialize or verify tenancy
        /** @var Tenant|null $tenant */
        $tenant = Tenant::find($apiKey->tenant_id);
        if (! $tenant || ! $tenant->isActive()) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'The merchant account associated with this key is inactive.',
            ], 403);
        }

        if (function_exists('tenancy') && tenancy()->initialized) {
            if ((string) tenant('id') !== (string) $apiKey->tenant_id) {
                return response()->json([
                    'error' => 'Forbidden',
                    'message' => 'API Key does not belong to the targeted store domain.',
                ], 403);
            }
        } elseif (function_exists('tenancy')) {
            tenancy()->initialize($tenant);
        }

        // Check plan feature: api
        if (! Features::enabled('api', $tenant)) {
            throw new FeatureNotAvailableException('api');
        }

        // Touch last used timestamp
        $apiKey->touchUsage();

        $request->attributes->set('api_key', $apiKey);
        $request->setUserResolver(fn () => $apiKey->owner);

        return $next($request);
    }
}
