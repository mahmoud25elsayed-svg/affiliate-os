<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Idempotency\IdempotencyKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireIdempotencyKey
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, int $ttlSeconds = 86400): Response
    {
        $key = $request->header('Idempotency-Key');

        if (! is_string($key) || trim($key) === '') {
            return $next($request);
        }

        $requestHash = hash('sha256', json_encode($request->all()) ?: '');
        $path = $request->path();
        $method = $request->method();

        $tenantId = function_exists('tenancy') && tenancy()->initialized ? (string) tenant('id') : null;

        $query = IdempotencyKey::where('key', $key);
        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        } else {
            $query->whereNull('tenant_id');
        }

        /** @var IdempotencyKey|null $existing */
        $existing = $query->first();

        if ($existing !== null) {
            if ($existing->expires_at === null || $existing->expires_at->isFuture()) {
                /** @var array<string, array<int, string>> $headers */
                $headers = $existing->response_headers ?? [];
                $headers['X-Idempotency-Replay'] = ['true'];

                return response((string) $existing->response_body, $existing->response_status, $headers);
            }

            $existing->delete();
        }

        $response = $next($request);

        IdempotencyKey::create([
            'tenant_id' => $tenantId,
            'key' => $key,
            'request_method' => $method,
            'request_path' => $path,
            'request_hash' => $requestHash,
            'response_status' => $response->getStatusCode(),
            'response_headers' => $response->headers->all(),
            'response_body' => $response->getContent() ?: '',
            'expires_at' => now()->addSeconds($ttlSeconds),
        ]);

        return $response;
    }
}
