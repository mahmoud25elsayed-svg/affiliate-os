<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Models\Tenant;
use App\Models\User;
use App\Policies\TenantResourcePolicy;
use App\Support\Tenancy\Permissions\TenantPermissionMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePolicyTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private TenantResourcePolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['id' => 'rbac-store', 'name' => 'RBAC Store', 'status' => 'active']);
        tenancy()->initialize($this->tenant);
        TenantPermissionMatrix::seedForTenant($this->tenant->id);

        $this->policy = new TenantResourcePolicy;
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }
        parent::tearDown();
    }

    private function createUserWithRole(string $role): User
    {
        $user = User::create([
            'name' => ucfirst($role).' User',
            'email' => "{$role}@rbac.test",
            'password' => 'secret',
            'tenant_id' => $this->tenant->id,
            'is_platform_admin' => false,
        ]);

        TenantPermissionMatrix::assignRole($user, $role, $this->tenant->id);

        return $user;
    }

    public function test_owner_role_has_full_permissions(): void
    {
        $owner = $this->createUserWithRole(TenantPermissionMatrix::ROLE_OWNER);

        $this->assertTrue($this->policy->viewSettings($owner));
        $this->assertTrue($this->policy->manageSettings($owner));
        $this->assertTrue($this->policy->manageStaff($owner));
        $this->assertTrue($this->policy->manageCatalog($owner));
        $this->assertTrue($this->policy->manageOrders($owner));
        $this->assertTrue($this->policy->transitionOrder($owner));
        $this->assertTrue($this->policy->manageFinance($owner));
        $this->assertTrue($this->policy->manageSupport($owner));
        $this->assertTrue($this->policy->callCustomer($owner));
    }

    public function test_manager_role_permissions(): void
    {
        $manager = $this->createUserWithRole(TenantPermissionMatrix::ROLE_MANAGER);

        $this->assertTrue($this->policy->viewSettings($manager));
        $this->assertTrue($this->policy->manageSettings($manager));
        $this->assertTrue($this->policy->manageStaff($manager));
        $this->assertTrue($this->policy->manageCatalog($manager));
        $this->assertTrue($this->policy->manageOrders($manager));
        $this->assertTrue($this->policy->transitionOrder($manager));
        $this->assertTrue($this->policy->manageSupport($manager));
        $this->assertTrue($this->policy->callCustomer($manager));

        // Manager does not have direct finance management permission
        $this->assertFalse($this->policy->manageFinance($manager));
    }

    public function test_catalog_role_permissions(): void
    {
        $catalogUser = $this->createUserWithRole(TenantPermissionMatrix::ROLE_CATALOG);

        $this->assertTrue($this->policy->viewCatalog($catalogUser));
        $this->assertTrue($this->policy->manageCatalog($catalogUser));
        $this->assertTrue($this->policy->viewSettings($catalogUser));

        // Must not manage orders or finance or staff
        $this->assertFalse($this->policy->manageOrders($catalogUser));
        $this->assertFalse($this->policy->manageFinance($catalogUser));
        $this->assertFalse($this->policy->manageStaff($catalogUser));
    }

    public function test_orders_role_permissions(): void
    {
        $ordersUser = $this->createUserWithRole(TenantPermissionMatrix::ROLE_ORDERS);

        $this->assertTrue($this->policy->viewOrders($ordersUser));
        $this->assertTrue($this->policy->manageOrders($ordersUser));
        $this->assertTrue($this->policy->transitionOrder($ordersUser));

        // Must not manage catalog or finance
        $this->assertFalse($this->policy->manageCatalog($ordersUser));
        $this->assertFalse($this->policy->manageFinance($ordersUser));
    }

    public function test_finance_role_permissions(): void
    {
        $financeUser = $this->createUserWithRole(TenantPermissionMatrix::ROLE_FINANCE);

        $this->assertTrue($this->policy->viewFinance($financeUser));
        $this->assertTrue($this->policy->manageFinance($financeUser));
        $this->assertTrue($this->policy->viewOrders($financeUser));

        // Must not manage catalog or transition orders
        $this->assertFalse($this->policy->manageCatalog($financeUser));
        $this->assertFalse($this->policy->transitionOrder($financeUser));
    }

    public function test_support_role_permissions(): void
    {
        $supportUser = $this->createUserWithRole(TenantPermissionMatrix::ROLE_SUPPORT);

        $this->assertTrue($this->policy->viewSupport($supportUser));
        $this->assertTrue($this->policy->manageSupport($supportUser));
        $this->assertTrue($this->policy->viewOrders($supportUser));

        // Must not manage finance or catalog
        $this->assertFalse($this->policy->manageFinance($supportUser));
        $this->assertFalse($this->policy->manageCatalog($supportUser));
    }

    public function test_call_agent_role_permissions(): void
    {
        $callAgent = $this->createUserWithRole(TenantPermissionMatrix::ROLE_CALL_AGENT);

        $this->assertTrue($this->policy->callCustomer($callAgent));
        $this->assertTrue($this->policy->transitionOrder($callAgent));
        $this->assertTrue($this->policy->viewOrders($callAgent));

        // Must not manage settings or finance or staff
        $this->assertFalse($this->policy->manageSettings($callAgent));
        $this->assertFalse($this->policy->manageFinance($callAgent));
        $this->assertFalse($this->policy->manageStaff($callAgent));
    }
}
