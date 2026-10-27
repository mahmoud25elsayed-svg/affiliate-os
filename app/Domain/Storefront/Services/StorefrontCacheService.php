<?php

declare(strict_types=1);

namespace App\Domain\Storefront\Services;

use Closure;
use Illuminate\Support\Facades\Cache;

class StorefrontCacheService
{
    /**
     * Remember storefront data tagged by the active tenant context.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function remember(string $key, int $ttlSeconds, Closure $callback): mixed
    {
        $tenantId = function_exists('tenancy') && tenancy()->initialized ? (string) tenant('id') : 'central';
        $cacheKey = "storefront.{$tenantId}.{$key}";

        return Cache::remember($cacheKey, $ttlSeconds, $callback);
    }

    /**
     * Invalidate all storefront cached data for the current tenant.
     */
    public static function flush(): void
    {
        $tenantId = function_exists('tenancy') && tenancy()->initialized ? (string) tenant('id') : 'central';

        Cache::forget("storefront.{$tenantId}.home_data");
        Cache::forget("storefront.{$tenantId}.categories");
        Cache::forget("storefront.{$tenantId}.featured_products");
        Cache::forget("storefront.{$tenantId}.sitemap");
    }
}
