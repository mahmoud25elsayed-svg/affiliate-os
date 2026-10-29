<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Models\Plan;
use App\Models\User;
use App\Support\Location\Governorate;
use Database\Seeders\ProductionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class LaunchReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_seeder_runs_idempotently_without_errors(): void
    {
        // First run
        $this->seed(ProductionSeeder::class);

        $this->assertDatabaseHas('lunar_languages', [
            'code' => 'ar',
            'default' => true,
        ]);

        $this->assertDatabaseHas('lunar_languages', [
            'code' => 'en',
            'default' => false,
        ]);

        $govCount = Governorate::count();
        $this->assertEquals(27, $govCount, 'Egypt Governorates must be 27 governorates.');

        $plansCount = Plan::count();
        $this->assertGreaterThanOrEqual(3, $plansCount, 'Subscription plans must contain at least 3 tiers.');

        // Second run to verify idempotency (must not duplicate or crash)
        $this->seed(ProductionSeeder::class);

        $this->assertEquals(27, Governorate::count());
        $this->assertEquals($plansCount, Plan::count());
    }

    public function test_create_super_admin_command_provisions_admin_with_2fa(): void
    {
        $email = 'executive.admin@affiliate-os.test';

        $exitCode = Artisan::call('platform:create-super-admin', [
            'email' => $email,
            '--name' => 'Executive Officer',
            '--password' => 'StrongAdminPass#2026',
            '--force' => true,
        ]);

        $this->assertEquals(0, $exitCode);

        /** @var User|null $admin */
        $admin = User::where('email', $email)->first();
        $this->assertNotNull($admin);
        $this->assertTrue($admin->isPlatformAdmin());
        $this->assertTrue($admin->hasTwoFactorEnabled());
        $this->assertNull($admin->tenant_id, 'Platform Super Admin must not belong to any tenant');

        $this->assertNotNull($admin->two_factor_secret);
        $this->assertEquals(32, strlen($admin->two_factor_secret));
        $this->assertNotNull($admin->two_factor_confirmed_at);

        $this->assertIsArray($admin->two_factor_recovery_codes);
        $this->assertCount(8, $admin->two_factor_recovery_codes);

        $output = Artisan::output();
        $this->assertStringContainsString('Platform Super Admin Configured with 2FA', $output);
        $this->assertStringContainsString('Recovery Codes', $output);
    }

    public function test_all_incident_runbooks_and_launch_checklist_exist_with_complete_guidelines(): void
    {
        $docs = [
            'docs/launch-checklist.md' => [
                'Production Launch Readiness',
                'OWASP Top 10 Review',
                'Production Seeding & Super Admin',
                'Backup & Recovery Drill',
                'Monitoring & Health Checks',
                'Legal Compliance Review',
                'Incident Response Runbooks',
                'First 2-Weeks Launch Plan',
            ],
            'docs/runbooks/incident-database-outage.md' => [
                'MySQL Database Outage & Recovery',
                'Sev-1 Critical',
                'Immediate Containment',
                'Too many connections',
                'Service Recovery',
            ],
            'docs/runbooks/incident-payment-gateway-failure.md' => [
                'Electronic Payment Gateway Failure',
                'Sev-2 High',
                'Immediate Containment',
                'Idempotent Reconciliation',
                'Paymob',
            ],
            'docs/runbooks/incident-ledger-discrepancy.md' => [
                'Double-Entry Ledger Imbalance',
                'Sev-1 Critical',
                'Zero Float & Absolute Balance',
                'COMPENSATING_ADJUSTMENT',
                'ledger:reconcile',
            ],
            'docs/runbooks/incident-data-breach.md' => [
                'Personal Data Breach & Egyptian Law 151/2020',
                'Sev-1 Critical',
                '72 ساعة',
                'مركز حماية البيانات الشخصية',
                'المادة (7)',
            ],
        ];

        foreach ($docs as $path => $requiredPhrases) {
            $fullPath = base_path($path);
            $this->assertFileExists($fullPath, "Runbook or checklist [{$path}] must exist.");

            $content = (string) File::get($fullPath);
            $this->assertGreaterThan(500, strlen($content), "Document [{$path}] must contain substantive runbook text.");

            foreach ($requiredPhrases as $phrase) {
                $this->assertStringContainsString(
                    $phrase,
                    $content,
                    "Document [{$path}] must contain required section/term [{$phrase}]."
                );
            }
        }
    }

    public function test_ledger_reconciliation_command_detects_clean_initial_state(): void
    {
        $exitCode = Artisan::call('ledger:reconcile');
        $this->assertEquals(0, $exitCode);
    }

    public function test_health_check_endpoint_returns_healthy_status(): void
    {
        Cache::forever('system:last_scheduler_run', now()->toIso8601String());

        $response = $this->get('/health');
        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'healthy',
        ]);
        $response->assertJsonStructure([
            'status',
            'timestamp',
            'checks' => [
                'database',
                'queue',
                'scheduler',
                'storage',
            ],
        ]);
    }
}
