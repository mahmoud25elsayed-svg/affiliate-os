<?php

declare(strict_types=1);

namespace App\Support\Billing;

use App\Models\Tenant;
use App\Models\User;
use App\Support\Billing\Exceptions\LimitExceededException;
use Lunar\Models\Order;
use Lunar\Models\Product;

class Limits
{
    public static function get(string $limit, ?Tenant $tenant = null): int
    {
        $tenant = Features::resolveTenant($tenant);

        if (! $tenant) {
            return 0;
        }

        $subscription = $tenant->subscription;

        if (! $subscription || ! $subscription->isUsable()) {
            return 0;
        }

        return $subscription->plan->getLimit($limit);
    }

    public static function usage(string $limit, ?Tenant $tenant = null): int
    {
        $tenant = Features::resolveTenant($tenant);

        if (! $tenant) {
            return 0;
        }

        return match ($limit) {
            'products' => self::countProducts($tenant),
            'monthly_orders' => self::countMonthlyOrders($tenant),
            'affiliates' => self::countAffiliates($tenant),
            'staff' => self::countStaff($tenant),
            'storage_mb' => self::calculateStorageMb($tenant),
            default => 0,
        };
    }

    public static function check(string $limit, ?Tenant $tenant = null, int $increment = 1): bool
    {
        $max = self::get($limit, $tenant);

        // -1 represents unlimited
        if ($max === -1) {
            return true;
        }

        if ($max <= 0) {
            return false;
        }

        $current = self::usage($limit, $tenant);

        return ($current + $increment) <= $max;
    }

    public static function ensure(string $limit, ?Tenant $tenant = null, int $increment = 1): void
    {
        $max = self::get($limit, $tenant);

        if ($max === -1) {
            return;
        }

        $current = self::usage($limit, $tenant);

        if (($current + $increment) > $max) {
            throw new LimitExceededException($limit, $max, $current);
        }
    }

    private static function countProducts(Tenant $tenant): int
    {
        if (function_exists('tenancy') && tenancy()->initialized && tenant('id') === $tenant->id) {
            return Product::count();
        }

        return (int) $tenant->run(fn () => Product::count());
    }

    private static function countMonthlyOrders(Tenant $tenant): int
    {
        $startOfMonth = now()->startOfMonth();

        if (function_exists('tenancy') && tenancy()->initialized && tenant('id') === $tenant->id) {
            return Order::where('created_at', '>=', $startOfMonth)->count();
        }

        return (int) $tenant->run(fn () => Order::where('created_at', '>=', $startOfMonth)->count());
    }

    private static function countAffiliates(Tenant $tenant): int
    {
        return User::where('tenant_id', $tenant->id)
            ->whereHas('roles', function ($query) use ($tenant) {
                $query->where('name', 'affiliate')->where('tenant_id', $tenant->id);
            })
            ->count();
    }

    private static function countStaff(Tenant $tenant): int
    {
        return User::where('tenant_id', $tenant->id)->count();
    }

    private static function calculateStorageMb(Tenant $tenant): int
    {
        // Simple tracked storage estimation
        return 10;
    }
}
