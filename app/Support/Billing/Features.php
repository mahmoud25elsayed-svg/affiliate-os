<?php

declare(strict_types=1);

namespace App\Support\Billing;

use App\Models\Subscription;
use App\Models\Tenant;
use App\Support\Billing\Exceptions\FeatureNotAvailableException;

class Features
{
    public static function enabled(string $feature, ?Tenant $tenant = null): bool
    {
        $tenant = self::resolveTenant($tenant);

        if (! $tenant) {
            return false;
        }

        // Must be active and not suspended
        if ($tenant->isSuspended()) {
            return false;
        }

        /** @var Subscription|null $subscription */
        $subscription = $tenant->subscription()->first();

        if (! $subscription || ! $subscription->isUsable()) {
            return false;
        }

        return $subscription->plan->hasFeature($feature);
    }

    public static function ensure(string $feature, ?Tenant $tenant = null): void
    {
        if (! self::enabled($feature, $tenant)) {
            throw new FeatureNotAvailableException($feature);
        }
    }

    public static function resolveTenant(?Tenant $tenant = null): ?Tenant
    {
        if ($tenant !== null) {
            return $tenant;
        }

        if (function_exists('tenancy') && tenancy()->initialized) {
            /** @var Tenant|null $t */
            $t = tenancy()->tenant;

            return $t;
        }

        if (function_exists('tenant') && tenant('id')) {
            /** @var Tenant|null $t */
            $t = Tenant::find(tenant('id'));

            return $t;
        }

        return null;
    }
}
