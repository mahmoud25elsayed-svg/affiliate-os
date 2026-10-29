<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure;

use App\Models\Tenant;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class HealthCheckAndDeploymentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $token = env('HEALTH_CHECK_TOKEN', 'affiliateos_health_sec_2026');
        $this->withHeader('X-Health-Token', (string) $token);

        // Clear health-related caches and temporary queue items
        Cache::forget('system:last_scheduler_run');
        Cache::forget('ledger:last_reconcile_status');
        DB::table('jobs')->delete();
        DB::table('tenants')->where('status', 'provisioning')->delete();
    }

    public function test_health_check_returns_healthy_200_under_normal_conditions(): void
    {
        // Record fresh scheduler heartbeat
        Cache::forever('system:last_scheduler_run', now()->toIso8601String());

        $response = $this->getJson('/health');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'timestamp',
                'environment',
                'checks' => [
                    'database' => ['status', 'latency_ms'],
                    'queue' => ['status', 'pending_jobs', 'oldest_job_age_seconds', 'recent_failed_jobs'],
                    'scheduler' => ['status', 'last_run', 'seconds_since_last_run'],
                    'storage' => ['status', 'free_bytes', 'free_percentage'],
                    'platform' => ['status', 'ledger_status', 'unprovisioned_tenants'],
                ],
            ]);

        $this->assertEquals('healthy', $response->json('status'));
        $this->assertEquals('healthy', $response->json('checks.database.status'));
        $this->assertEquals('healthy', $response->json('checks.queue.status'));
        $this->assertEquals('healthy', $response->json('checks.scheduler.status'));
    }

    public function test_health_check_returns_degraded_when_oldest_job_between_60_and_120_seconds(): void
    {
        Cache::forever('system:last_scheduler_run', now()->toIso8601String());

        // Insert job aged 90 seconds
        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => json_encode(['job' => 'DummyJob']),
            'attempts' => 0,
            'available_at' => time() - 90,
            'created_at' => time() - 90,
        ]);

        $response = $this->getJson('/health');

        $response->assertStatus(200);
        $this->assertEquals('degraded', $response->json('status'));
        $this->assertEquals('degraded', $response->json('checks.queue.status'));
        $this->assertGreaterThanOrEqual(85, $response->json('checks.queue.oldest_job_age_seconds'));
    }

    public function test_health_check_returns_unhealthy_503_when_oldest_job_exceeds_120_seconds(): void
    {
        Cache::forever('system:last_scheduler_run', now()->toIso8601String());

        // Insert job aged 150 seconds (> 120s SLA threshold)
        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => json_encode(['job' => 'LaggingJob']),
            'attempts' => 0,
            'available_at' => time() - 150,
            'created_at' => time() - 150,
        ]);

        $response = $this->getJson('/health');

        $response->assertStatus(503);
        $this->assertEquals('unhealthy', $response->json('status'));
        $this->assertEquals('unhealthy', $response->json('checks.queue.status'));
        $this->assertGreaterThanOrEqual(140, $response->json('checks.queue.oldest_job_age_seconds'));
    }

    public function test_health_check_detects_ledger_discrepancies(): void
    {
        Cache::forever('system:last_scheduler_run', now()->toIso8601String());
        Cache::put('ledger:last_reconcile_status', [
            'healthy' => false,
            'discrepancies_count' => 3,
            'checked_at' => now()->toIso8601String(),
        ]);

        $response = $this->getJson('/health');

        $response->assertStatus(503);
        $this->assertEquals('unhealthy', $response->json('status'));
        $this->assertEquals('discrepancy_detected', $response->json('checks.platform.ledger_status'));
    }

    public function test_health_check_detects_stuck_unprovisioned_tenants(): void
    {
        Cache::forever('system:last_scheduler_run', now()->toIso8601String());

        // Create a stuck tenant (unprovisioned for > 15 mins)
        $tenantId = 'stuck-tenant-'.uniqid();
        DB::table('tenants')->insert([
            'id' => $tenantId,
            'name' => 'Stuck Merchant',
            'status' => 'provisioning',
            'created_at' => now()->subMinutes(30)->toDateTimeString(),
            'updated_at' => now()->subMinutes(30)->toDateTimeString(),
        ]);

        $response = $this->getJson('/health');

        $response->assertStatus(200);
        $this->assertEquals('degraded', $response->json('status'));
        $this->assertGreaterThanOrEqual(1, $response->json('checks.platform.unprovisioned_tenants'));

        DB::table('tenants')->where('id', $tenantId)->delete();
    }

    public function test_backup_database_command_creates_compressed_and_encrypted_archive_with_checksum(): void
    {
        $backupDir = storage_path('app/testing_backups_'.uniqid());

        $exitCode = Artisan::call('app:backup-database', [
            '--destination' => $backupDir,
            '--encrypt' => true,
            '--encryption-key' => 'test-secret-backup-key-32-chars-long!',
            '--skip-storage' => true,
            '--retention-days' => 14,
        ]);

        $this->assertEquals(0, $exitCode);
        $this->assertTrue(File::isDirectory($backupDir));

        $files = File::files($backupDir);
        $this->assertNotEmpty($files);

        $encFiles = array_filter($files, fn ($f) => str_ends_with($f->getFilename(), '.sql.gz.enc'));
        $shaFiles = array_filter($files, fn ($f) => str_ends_with($f->getFilename(), '.sha256'));

        $this->assertCount(1, $encFiles, 'Encrypted backup archive must exist.');
        $this->assertCount(1, $shaFiles, 'SHA-256 checksum file must exist.');

        $encFile = reset($encFiles);
        $shaFile = reset($shaFiles);

        $this->assertGreaterThan(0, $encFile->getSize());

        // Verify SHA-256 integrity
        $expectedHash = hash_file('sha256', $encFile->getPathname());
        $checksumContent = File::get($shaFile->getPathname());
        $this->assertStringContainsString($expectedHash, $checksumContent);

        // Test disaster recovery restoration
        $restoreExit = Artisan::call('app:restore-database', [
            'file' => $encFile->getPathname(),
            '--encryption-key' => 'test-secret-backup-key-32-chars-long!',
            '--force' => true,
        ]);
        $this->assertEquals(0, $restoreExit, 'Disaster recovery restoration must complete successfully.');

        // Clean up temporary testing backups
        File::deleteDirectory($backupDir);
    }

    public function test_backup_database_command_prunes_outdated_backups(): void
    {
        $backupDir = storage_path('app/testing_backups_prune_'.uniqid());
        File::makeDirectory($backupDir, 0755, true);

        $oldFile = "{$backupDir}/backup_ancient.sql.gz.enc";
        File::put($oldFile, 'ancient backup dummy data');
        touch($oldFile, now()->subDays(20)->timestamp);

        $recentFile = "{$backupDir}/backup_recent.sql.gz.enc";
        File::put($recentFile, 'recent backup dummy data');
        touch($recentFile, now()->subDays(2)->timestamp);

        Artisan::call('app:backup-database', [
            '--destination' => $backupDir,
            '--skip-storage' => true,
            '--retention-days' => 7,
        ]);

        $this->assertFalse(File::exists($oldFile), 'File older than retention-days must be pruned.');
        $this->assertTrue(File::exists($recentFile), 'Recent file within retention-days must be preserved.');

        File::deleteDirectory($backupDir);
    }

    public function test_deployment_and_rollback_scripts_exist(): void
    {
        $deployScript = base_path('scripts/deploy/deploy.sh');
        $rollbackScript = base_path('scripts/deploy/rollback.sh');

        $this->assertFileExists($deployScript);
        $this->assertFileExists($rollbackScript);

        $deployContent = (string) file_get_contents($deployScript);
        $rollbackContent = (string) file_get_contents($rollbackScript);

        $this->assertStringContainsString('symlink swap', strtolower($deployContent));
        $this->assertStringContainsString('health', strtolower($deployContent));
        $this->assertStringContainsString('symlink swap', strtolower($rollbackContent));
        $this->assertStringContainsString('previous_release', strtolower($rollbackContent));
    }
}
