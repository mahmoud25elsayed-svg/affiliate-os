<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Models\Tenant;
use App\Models\User;
use App\Support\Audit\AuditLog;
use App\Support\Auth\ImpersonationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class TenantImpersonationTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_platform_admin_cannot_impersonate_tenant(): void
    {
        $tenant = Tenant::create(['id' => 'target-tenant', 'name' => 'Target', 'status' => 'active']);
        $regularUser = User::create([
            'name' => 'Regular User',
            'email' => 'regular@user.test',
            'password' => 'secret',
            'is_platform_admin' => false,
        ]);

        $service = app(ImpersonationService::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Only platform administrators can impersonate tenants.');

        $service->impersonate($regularUser, $tenant);
    }

    public function test_platform_admin_can_impersonate_tenant_and_is_logged_in_audit(): void
    {
        $tenant = Tenant::create(['id' => 'alpha-store', 'name' => 'Alpha Store', 'status' => 'active']);
        $owner = User::create([
            'name' => 'Alpha Owner',
            'email' => 'owner@alpha.test',
            'password' => 'secret',
            'tenant_id' => $tenant->id,
            'is_platform_admin' => false,
        ]);

        $admin = User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@platform.test',
            'password' => 'secret',
            'is_platform_admin' => true,
        ]);

        $service = app(ImpersonationService::class);
        $targetUser = $service->impersonate($admin, $tenant, durationMinutes: 30);

        $this->assertEquals($owner->id, $targetUser->id);
        $this->assertTrue($service->isImpersonating());
        $this->assertGreaterThan(0, $service->getRemainingSeconds());

        // Check Audit log entry
        $audit = AuditLog::where('action', 'tenant.impersonate')->first();
        $this->assertNotNull($audit);
        $this->assertEquals((string) $admin->id, (string) $audit->actor_id);
        $this->assertEquals('alpha-store', $audit->tenant_id);
        $this->assertEquals('alpha-store', $audit->after['tenant_id']);
    }

    public function test_impersonation_can_be_stopped_and_restores_admin(): void
    {
        $tenant = Tenant::create(['id' => 'beta-store', 'name' => 'Beta Store', 'status' => 'active']);
        $owner = User::create([
            'name' => 'Beta Owner',
            'email' => 'owner@beta.test',
            'password' => 'secret',
            'tenant_id' => $tenant->id,
            'is_platform_admin' => false,
        ]);

        $admin = User::create([
            'name' => 'Central Admin',
            'email' => 'admin@central.test',
            'password' => 'secret',
            'is_platform_admin' => true,
        ]);

        $service = app(ImpersonationService::class);
        $service->impersonate($admin, $tenant);

        $this->assertTrue($service->isImpersonating());

        // Stop impersonation via POST /impersonation/stop
        $response = $this->actingAs($owner)->post(route('impersonation.stop'));
        $response->assertRedirect('/platform');

        $this->assertFalse($service->isImpersonating());
        $this->assertEquals($admin->id, Auth::id());

        // Check Audit ended log
        $audit = AuditLog::where('action', 'tenant.impersonate.ended')->first();
        $this->assertNotNull($audit);
        $this->assertEquals((string) $admin->id, (string) $audit->actor_id);
        $this->assertEquals('beta-store', $audit->tenant_id);
    }

    public function test_impersonation_automatically_expires_after_time_limit(): void
    {
        $tenant = Tenant::create(['id' => 'gamma-store', 'name' => 'Gamma Store', 'status' => 'active']);
        User::create([
            'name' => 'Gamma Owner',
            'email' => 'owner@gamma.test',
            'password' => 'secret',
            'tenant_id' => $tenant->id,
            'is_platform_admin' => false,
        ]);

        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@gamma-platform.test',
            'password' => 'secret',
            'is_platform_admin' => true,
        ]);

        $service = app(ImpersonationService::class);
        $service->impersonate($admin, $tenant, durationMinutes: 60);

        $this->assertTrue($service->isImpersonating());

        // Simulate time travel past 60 minutes
        session()->put(ImpersonationService::SESSION_EXPIRES_AT, time() - 10);

        $this->assertFalse($service->isImpersonating());
    }
}
