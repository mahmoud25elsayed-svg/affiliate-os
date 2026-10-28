<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireApiKeyScope
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string ...$scopes): Response
    {
        /** @var ApiKey|null $apiKey */
        $apiKey = $request->attributes->get('api_key');

        if (! $apiKey) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'API Key authentication is required.',
            ], 401);
        }

        foreach ($scopes as $scope) {
            if (! $apiKey->hasScope($scope)) {
                return response()->json([
                    'error' => 'Forbidden',
                    'message' => "API Key lacks required scope: [{$scope}].",
                ], 403);
            }
        }

        return $next($request);
    }
}
