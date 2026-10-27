<?php

declare(strict_types=1);

namespace App\Support\Tenancy\Bootstrappers;

use Illuminate\Cache\CacheManager;

class PrefixTenantCacheManager
{
    public function __construct(
        protected CacheManager $cacheManager,
        protected string $tenantId
    ) {}

    public function prefixKey(string $key): string
    {
        return "tenant_{$this->tenantId}:{$key}";
    }

    /**
     * @param  array<int, mixed>  $parameters
     */
    public function __call(string $method, array $parameters): mixed
    {
        if (! empty($parameters) && is_string($parameters[0]) && ! in_array($method, ['store', 'driver'], true)) {
            $parameters[0] = $this->prefixKey($parameters[0]);
        }

        return $this->cacheManager->$method(...$parameters);
    }
}
