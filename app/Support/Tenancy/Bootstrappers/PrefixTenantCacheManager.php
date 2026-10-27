<?php

declare(strict_types=1);

namespace App\Support\Tenancy\Bootstrappers;

use Illuminate\Cache\CacheManager;

class PrefixTenantCacheManager extends CacheManager
{
    public function __construct(
        protected CacheManager $cacheManager,
        protected string $tenantId
    ) {
        parent::__construct(app());
    }

    public function prefixKey(string $key): string
    {
        return "tenant_{$this->tenantId}:{$key}";
    }

    public function store($name = null)
    {
        return $this->cacheManager->store($name);
    }

    public function driver($driver = null)
    {
        return $this->cacheManager->driver($driver);
    }

    /**
     * @param  string  $method
     * @param  array<int, mixed>  $parameters
     */
    public function __call($method, $parameters): mixed
    {
        if (! empty($parameters) && is_string($parameters[0]) && ! in_array($method, ['store', 'driver'], true)) {
            $parameters[0] = $this->prefixKey($parameters[0]);
        }

        return $this->cacheManager->$method(...$parameters);
    }
}
