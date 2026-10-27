<?php

declare(strict_types=1);

namespace App\Support\Idempotency;

use Closure;
use Illuminate\Support\Facades\DB;

class IdempotencyService
{
    /**
     * Run an arbitrary callback idempotently based on a unique key.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function run(string $key, Closure $callback, int $ttlSeconds = 86400): mixed
    {
        return DB::transaction(function () use ($key, $callback, $ttlSeconds) {
            $tenantId = function_exists('tenancy') && tenancy()->initialized ? (string) tenant('id') : null;

            $query = IdempotencyKey::where('key', $key);
            if ($tenantId !== null) {
                $query->where('tenant_id', $tenantId);
            } else {
                $query->whereNull('tenant_id');
            }

            /** @var IdempotencyKey|null $existing */
            $existing = $query->lockForUpdate()->first();

            if ($existing !== null) {
                if ($existing->expires_at === null || $existing->expires_at->isFuture()) {
                    return unserialize((string) $existing->response_body);
                }

                $existing->delete();
            }

            $result = $callback();

            IdempotencyKey::create([
                'tenant_id' => $tenantId,
                'key' => $key,
                'request_method' => 'SERVICE',
                'request_path' => 'service::run',
                'request_hash' => hash('sha256', $key),
                'response_status' => 200,
                'response_body' => serialize($result),
                'expires_at' => now()->addSeconds($ttlSeconds),
            ]);

            return $result;
        });
    }
}
