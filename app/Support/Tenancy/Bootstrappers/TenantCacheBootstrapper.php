<?php

declare(strict_types=1);

namespace App\Support\Tenancy\Bootstrappers;

use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Cache;
use Stancl\Tenancy\Contracts\TenancyBootstrapper;
use Stancl\Tenancy\Contracts\Tenant;

class TenantCacheBootstrapper implements TenancyBootstrapper
{
    protected ?CacheManager $originalCache = null;

    public function __construct(protected Application $app) {}

    public function bootstrap(Tenant $tenant): void
    {
        $this->resetFacadeCache();

        $this->originalCache = $this->originalCache ?? $this->app->make('cache');

        $this->app->extend('cache', function ($service) use ($tenant) {
            $baseManager = $service instanceof PrefixTenantCacheManager ? $this->originalCache : $service;

            return new PrefixTenantCacheManager($baseManager, (string) $tenant->getTenantKey());
        });
    }

    public function revert(): void
    {
        $this->resetFacadeCache();

        if ($this->originalCache !== null) {
            $this->app->extend('cache', fn () => $this->originalCache);
            $this->originalCache = null;
        }
    }

    protected function resetFacadeCache(): void
    {
        Cache::clearResolvedInstances();
    }
}
