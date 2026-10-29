<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class HealthCheckController extends Controller
{
    /**
     * Check system health across database, queue, scheduler, storage, and platform operations.
     */
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'queue' => $this->checkQueue(),
            'scheduler' => $this->checkScheduler(),
            'storage' => $this->checkStorage(),
            'platform' => $this->checkPlatform(),
        ];

        $overallStatus = 'healthy';

        foreach ($checks as $component => $result) {
            $status = $result['status'];

            if ($status === 'unhealthy') {
                $overallStatus = 'unhealthy';
                break;
            }

            if ($status === 'degraded' && $overallStatus === 'healthy') {
                $overallStatus = 'degraded';
            }
        }

        $httpCode = ($overallStatus === 'unhealthy') ? 503 : 200;

        return response()->json([
            'status' => $overallStatus,
            'timestamp' => now()->toIso8601String(),
            'environment' => config('app.env'),
            'checks' => $checks,
        ], $httpCode);
    }

    /**
     * @return array{status: string, latency_ms: float, error?: string}
     */
    protected function checkDatabase(): array
    {
        $start = microtime(true);

        try {
            DB::connection()->getPdo();
            DB::select('SELECT 1');
            $latency = round((microtime(true) - $start) * 1000, 2);

            return [
                'status' => 'healthy',
                'latency_ms' => $latency,
            ];
        } catch (Throwable $e) {
            Log::critical('Health Check: Database connection failure: '.$e->getMessage());

            return [
                'status' => 'unhealthy',
                'latency_ms' => round((microtime(true) - $start) * 1000, 2),
                'error' => 'Database connection failed',
            ];
        }
    }

    /**
     * @return array{status: string, pending_jobs: int, oldest_job_age_seconds: int, recent_failed_jobs: int, error?: string}
     */
    protected function checkQueue(): array
    {
        try {
            $pendingCount = DB::table('jobs')->count();
            $oldestJob = DB::table('jobs')->orderBy('created_at', 'asc')->first();

            $oldestAge = 0;
            if ($oldestJob !== null) {
                $oldestAge = max(0, time() - (int) $oldestJob->created_at);
            }

            $recentFailedCount = DB::table('failed_jobs')
                ->where('failed_at', '>=', now()->subHour())
                ->count();

            $status = 'healthy';
            if ($oldestAge > 120) {
                $status = 'unhealthy';
                Log::critical("Health Check Alert: Oldest queue job age ({$oldestAge}s) exceeded 120s limit!", [
                    'pending_jobs' => $pendingCount,
                    'oldest_job_age_seconds' => $oldestAge,
                ]);
            } elseif ($oldestAge > 60 || $recentFailedCount > 10) {
                $status = 'degraded';
                Log::warning("Health Check Warning: Queue backlogged or recent failures detected ({$oldestAge}s, {$recentFailedCount} failed).");
            }

            return [
                'status' => $status,
                'pending_jobs' => $pendingCount,
                'oldest_job_age_seconds' => $oldestAge,
                'recent_failed_jobs' => $recentFailedCount,
            ];
        } catch (Throwable $e) {
            Log::critical('Health Check: Queue check failed: '.$e->getMessage());

            return [
                'status' => 'unhealthy',
                'pending_jobs' => 0,
                'oldest_job_age_seconds' => 0,
                'recent_failed_jobs' => 0,
                'error' => 'Queue inspection failed',
            ];
        }
    }

    /**
     * @return array{status: string, last_run: ?string, seconds_since_last_run: ?int}
     */
    protected function checkScheduler(): array
    {
        /** @var string|null $lastRun */
        $lastRun = Cache::get('system:last_scheduler_run');

        if (! $lastRun) {
            return [
                'status' => 'degraded',
                'last_run' => null,
                'seconds_since_last_run' => null,
            ];
        }

        try {
            $lastRunCarbon = Carbon::parse($lastRun);
            $secondsSince = max(0, (int) now()->diffInSeconds($lastRunCarbon));

            // Cron runs every minute; if > 180s (3 missed runs), report degraded
            $status = ($secondsSince > 180) ? 'degraded' : 'healthy';

            if ($secondsSince > 600) {
                $status = 'unhealthy';
                Log::critical("Health Check Alert: Scheduled cron has not run in {$secondsSince} seconds!");
            }

            return [
                'status' => $status,
                'last_run' => $lastRun,
                'seconds_since_last_run' => $secondsSince,
            ];
        } catch (Throwable) {
            return [
                'status' => 'degraded',
                'last_run' => $lastRun,
                'seconds_since_last_run' => null,
            ];
        }
    }

    /**
     * @return array{status: string, free_bytes: ?int, free_percentage: ?float}
     */
    protected function checkStorage(): array
    {
        $storagePath = storage_path();
        $freeBytes = @disk_free_space($storagePath);
        $totalBytes = @disk_total_space($storagePath);

        if ($freeBytes === false || $totalBytes === false || $totalBytes <= 0) {
            return [
                'status' => 'healthy',
                'free_bytes' => null,
                'free_percentage' => null,
            ];
        }

        $freePercentage = round(($freeBytes / $totalBytes) * 100, 2);

        $status = 'healthy';
        if ($freePercentage < 5.0) {
            $status = 'unhealthy';
            Log::critical("Health Check Alert: Critical disk storage space remaining ({$freePercentage}%)!");
        } elseif ($freePercentage < 15.0) {
            $status = 'degraded';
            Log::warning("Health Check Warning: Low disk storage space remaining ({$freePercentage}%).");
        }

        return [
            'status' => $status,
            'free_bytes' => (int) $freeBytes,
            'free_percentage' => $freePercentage,
        ];
    }

    /**
     * @return array{status: string, ledger_status: string, unprovisioned_tenants: int}
     */
    protected function checkPlatform(): array
    {
        $status = 'healthy';

        // 1. Ledger audit check
        /** @var array{healthy?: bool, discrepancies_count?: int, checked_at?: string}|null $ledgerReconcile */
        $ledgerReconcile = Cache::get('ledger:last_reconcile_status');

        $ledgerStatus = 'not_audited_yet';
        if ($ledgerReconcile !== null) {
            $isHealthy = $ledgerReconcile['healthy'] ?? true;
            $discrepanciesCount = (int) ($ledgerReconcile['discrepancies_count'] ?? 0);

            if (! $isHealthy || $discrepanciesCount > 0) {
                $ledgerStatus = 'discrepancy_detected';
                $status = 'unhealthy';
                Log::critical('Health Check Alert: Ledger discrepancies detected during reconciliation!');
            } else {
                $ledgerStatus = 'in_balance';
            }
        }

        // 2. Unprovisioned tenant queue check
        $unprovisionedCount = 0;

        try {
            $unprovisionedCount = DB::table('tenants')
                ->where('status', 'provisioning')
                ->where('created_at', '<=', now()->subMinutes(15))
                ->count();

            if ($unprovisionedCount > 0) {
                $status = 'degraded';
                Log::critical("Health Check Alert: {$unprovisionedCount} tenants stuck in provisioning for > 15 minutes!");
            }
        } catch (Throwable) {
            // Ignore if tenants table not accessible or during partial test setups
        }

        return [
            'status' => $status,
            'ledger_status' => $ledgerStatus,
            'unprovisioned_tenants' => $unprovisionedCount,
        ];
    }
}
