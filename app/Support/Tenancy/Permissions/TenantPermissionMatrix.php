<?php

namespace App\Support\Tenancy\Permissions;

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class TenantPermissionMatrix
{
    public const ROLE_OWNER = 'owner';

    public const ROLE_MANAGER = 'manager';

    public const ROLE_CATALOG = 'catalog';

    public const ROLE_ORDERS = 'orders';

    public const ROLE_FINANCE = 'finance';

    public const ROLE_SUPPORT = 'support';

    public const ROLE_CALL_AGENT = 'call_agent';

    public const PERM_SETTINGS_VIEW = 'store.settings.view';

    public const PERM_SETTINGS_UPDATE = 'store.settings.update';

    public const PERM_STAFF_VIEW = 'store.staff.view';

    public const PERM_STAFF_MANAGE = 'store.staff.manage';

    public const PERM_CATALOG_VIEW = 'store.catalog.view';

    public const PERM_CATALOG_MANAGE = 'store.catalog.manage';

    public const PERM_ORDERS_VIEW = 'store.orders.view';

    public const PERM_ORDERS_MANAGE = 'store.orders.manage';

    public const PERM_ORDERS_TRANSITION = 'store.orders.transition';

    public const PERM_FINANCE_VIEW = 'store.finance.view';

    public const PERM_FINANCE_MANAGE = 'store.finance.manage';

    public const PERM_SUPPORT_VIEW = 'store.support.view';

    public const PERM_SUPPORT_MANAGE = 'store.support.manage';

    public const PERM_CALL_CENTER_VIEW = 'store.call_center.view';

    public const PERM_CALL_CENTER_CONFIRM = 'store.call_center.confirm';

    public const PERM_KYC_VIEW = 'kyc.view';

    public const PERM_AFFILIATES_MANAGE = 'store.affiliates.manage';

    public const PERM_REPORTS_VIEW = 'store.reports.view';

    /**
     * @return list<string>
     */
    public static function roles(): array
    {
        return [
            self::ROLE_OWNER,
            self::ROLE_MANAGER,
            self::ROLE_CATALOG,
            self::ROLE_ORDERS,
            self::ROLE_FINANCE,
            self::ROLE_SUPPORT,
            self::ROLE_CALL_AGENT,
        ];
    }

    /**
     * @return list<string>
     */
    public static function permissions(): array
    {
        return [
            self::PERM_SETTINGS_VIEW,
            self::PERM_SETTINGS_UPDATE,
            self::PERM_STAFF_VIEW,
            self::PERM_STAFF_MANAGE,
            self::PERM_CATALOG_VIEW,
            self::PERM_CATALOG_MANAGE,
            self::PERM_ORDERS_VIEW,
            self::PERM_ORDERS_MANAGE,
            self::PERM_ORDERS_TRANSITION,
            self::PERM_FINANCE_VIEW,
            self::PERM_FINANCE_MANAGE,
            self::PERM_SUPPORT_VIEW,
            self::PERM_SUPPORT_MANAGE,
            self::PERM_CALL_CENTER_VIEW,
            self::PERM_CALL_CENTER_CONFIRM,
            self::PERM_KYC_VIEW,
            self::PERM_AFFILIATES_MANAGE,
            self::PERM_REPORTS_VIEW,
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public static function rolePermissions(): array
    {
        return [
            self::ROLE_OWNER => self::permissions(),

            self::ROLE_MANAGER => [
                self::PERM_SETTINGS_VIEW,
                self::PERM_SETTINGS_UPDATE,
                self::PERM_STAFF_VIEW,
                self::PERM_STAFF_MANAGE,
                self::PERM_CATALOG_VIEW,
                self::PERM_CATALOG_MANAGE,
                self::PERM_ORDERS_VIEW,
                self::PERM_ORDERS_MANAGE,
                self::PERM_ORDERS_TRANSITION,
                self::PERM_SUPPORT_VIEW,
                self::PERM_SUPPORT_MANAGE,
                self::PERM_CALL_CENTER_VIEW,
                self::PERM_CALL_CENTER_CONFIRM,
                self::PERM_KYC_VIEW,
                self::PERM_AFFILIATES_MANAGE,
                self::PERM_REPORTS_VIEW,
            ],

            self::ROLE_CATALOG => [
                self::PERM_SETTINGS_VIEW,
                self::PERM_CATALOG_VIEW,
                self::PERM_CATALOG_MANAGE,
            ],

            self::ROLE_ORDERS => [
                self::PERM_CATALOG_VIEW,
                self::PERM_ORDERS_VIEW,
                self::PERM_ORDERS_MANAGE,
                self::PERM_ORDERS_TRANSITION,
            ],

            self::ROLE_FINANCE => [
                self::PERM_ORDERS_VIEW,
                self::PERM_FINANCE_VIEW,
                self::PERM_FINANCE_MANAGE,
                self::PERM_REPORTS_VIEW,
            ],

            self::ROLE_SUPPORT => [
                self::PERM_CATALOG_VIEW,
                self::PERM_ORDERS_VIEW,
                self::PERM_SUPPORT_VIEW,
                self::PERM_SUPPORT_MANAGE,
            ],

            self::ROLE_CALL_AGENT => [
                self::PERM_ORDERS_VIEW,
                self::PERM_ORDERS_TRANSITION,
                self::PERM_CALL_CENTER_VIEW,
                self::PERM_CALL_CENTER_CONFIRM,
            ],
        ];
    }

    /**
     * Seed all roles and permissions for a given tenant.
     */
    public static function seedForTenant(string $tenantId): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($tenantId);

        // Ensure global/guard permissions exist
        foreach (self::permissions() as $permName) {
            Permission::firstOrCreate([
                'name' => $permName,
                'guard_name' => 'web',
            ]);
        }

        // Seed roles scoped to tenant
        $rolePermissions = self::rolePermissions();
        foreach ($rolePermissions as $roleName => $perms) {
            $role = Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
                'tenant_id' => $tenantId,
            ]);

            $role->syncPermissions($perms);
        }
    }

    /**
     * Assign a tenant role to a user.
     */
    public static function assignRole(User $user, string $roleName, ?string $tenantId = null): void
    {
        $tenantId ??= $user->tenant_id;
        if (! $tenantId) {
            return;
        }

        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($tenantId);

        self::seedForTenant($tenantId);

        $user->assignRole($roleName);
    }

    /**
     * Check if a user has a permission within tenant context.
     */
    public static function userHasPermission(User $user, string $permission, ?string $tenantId = null): bool
    {
        if ($user->is_platform_admin) {
            return true;
        }

        $tenantId ??= $user->tenant_id;
        if (! $tenantId) {
            return false;
        }

        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($tenantId);

        return $user->hasPermissionTo($permission, 'web');
    }

    /**
     * Check if a user has a specific role in a tenant.
     */
    public static function userHasRole(User $user, string $role, ?string $tenantId = null): bool
    {
        if ($user->is_platform_admin && $role === self::ROLE_OWNER) {
            return true;
        }

        $tenantId ??= $user->tenant_id;
        if (! $tenantId) {
            return false;
        }

        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($tenantId);

        return $user->hasRole($role, 'web');
    }
}
