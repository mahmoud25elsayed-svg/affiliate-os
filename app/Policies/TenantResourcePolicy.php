<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Support\Tenancy\Permissions\TenantPermissionMatrix;

class TenantResourcePolicy
{
    public function viewSettings(User $user): bool
    {
        return TenantPermissionMatrix::userHasPermission($user, TenantPermissionMatrix::PERM_SETTINGS_VIEW);
    }

    public function manageSettings(User $user): bool
    {
        return TenantPermissionMatrix::userHasPermission($user, TenantPermissionMatrix::PERM_SETTINGS_UPDATE);
    }

    public function viewStaff(User $user): bool
    {
        return TenantPermissionMatrix::userHasPermission($user, TenantPermissionMatrix::PERM_STAFF_VIEW);
    }

    public function manageStaff(User $user): bool
    {
        return TenantPermissionMatrix::userHasPermission($user, TenantPermissionMatrix::PERM_STAFF_MANAGE);
    }

    public function viewCatalog(User $user): bool
    {
        return TenantPermissionMatrix::userHasPermission($user, TenantPermissionMatrix::PERM_CATALOG_VIEW);
    }

    public function manageCatalog(User $user): bool
    {
        return TenantPermissionMatrix::userHasPermission($user, TenantPermissionMatrix::PERM_CATALOG_MANAGE);
    }

    public function viewOrders(User $user): bool
    {
        return TenantPermissionMatrix::userHasPermission($user, TenantPermissionMatrix::PERM_ORDERS_VIEW);
    }

    public function manageOrders(User $user): bool
    {
        return TenantPermissionMatrix::userHasPermission($user, TenantPermissionMatrix::PERM_ORDERS_MANAGE);
    }

    public function transitionOrder(User $user): bool
    {
        return TenantPermissionMatrix::userHasPermission($user, TenantPermissionMatrix::PERM_ORDERS_TRANSITION);
    }

    public function viewFinance(User $user): bool
    {
        return TenantPermissionMatrix::userHasPermission($user, TenantPermissionMatrix::PERM_FINANCE_VIEW);
    }

    public function manageFinance(User $user): bool
    {
        return TenantPermissionMatrix::userHasPermission($user, TenantPermissionMatrix::PERM_FINANCE_MANAGE);
    }

    public function viewSupport(User $user): bool
    {
        return TenantPermissionMatrix::userHasPermission($user, TenantPermissionMatrix::PERM_SUPPORT_VIEW);
    }

    public function manageSupport(User $user): bool
    {
        return TenantPermissionMatrix::userHasPermission($user, TenantPermissionMatrix::PERM_SUPPORT_MANAGE);
    }

    public function callCustomer(User $user): bool
    {
        return TenantPermissionMatrix::userHasPermission($user, TenantPermissionMatrix::PERM_CALL_CENTER_CONFIRM);
    }
}
