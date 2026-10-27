<?php

declare(strict_types=1);

namespace App\Support\Tenancy\Bootstrappers;

use Illuminate\Support\Facades\Log;
use Stancl\Tenancy\Contracts\TenancyBootstrapper;
use Stancl\Tenancy\Contracts\Tenant;

class LoggingTenancyBootstrapper implements TenancyBootstrapper
{
    public function bootstrap(Tenant $tenant): void
    {
        Log::shareContext([
            'tenant_id' => $tenant->getTenantKey(),
        ]);
    }

    public function revert(): void
    {
        Log::shareContext([
            'tenant_id' => null,
        ]);
    }
}
