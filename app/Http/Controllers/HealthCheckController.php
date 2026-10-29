<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class HealthCheckController extends Controller
{
    /**
     * Check system health across database, queue, scheduler, storage, and platform operations.
     */
    public function __invoke(Request $request): JsonResponse
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

        // Security (GEN-02 / SC-09): verbose telemetry only for authorized callers. Header/Bearer only —
        // query-string tokens leak into access logs.
        $token = $request->header('X-Health-Token') ?? $request->bearerToken();
        $expectedToken = (string) config('services.health.token', '');
        $isAuthorized = ($expectedToken !== '' && is_string($token) && $token !== '' && hash_equals($expectedToken, $token))
            || (bool) (auth()->user()->is_platform_admin ?? false);

        if ($isAuthorized) {
            $checksOutput = $checks;
        } else {
            // Mask raw internal disk bytes, job IDs, and unprovisioned tenant count
            $checksOutput = [
                'database' => ['status' => $checks['database']['status']],
                'queue' => ['status' => $checks['queue']['status']],
                'scheduler' => [
                    'status' => $checks['scheduler']['status'],
                    'last_run' => $checks['scheduler']['last_run'] ?? null,
                ],
                'storage' => ['status' => $checks['storage']['status']],
                'platform' => ['status' => $checks['platform']['status']],
            ];
        }

        return response()->json([
            'status' => $overallStatus,
            'timestamp' => now()->toIso8601String(),
            'environment' => config('app.env'),
            'checks' => $checksOutput,
        ], $httpCode);
    }

    /**
     * Rate-limited critical logger to prevent log flooding during persistent unhealthy states.
     *
     * @param  array<string, mixed>  $context
     */
    protected function logCriticalThrottled(string $key, string $message, array $context = [], int $ttlSeconds = 300): void
    {
        $cacheKey = 'health_crit_logged_'.md5($key);
        if (Cache::add($cacheKey, true, $ttlSeconds)) {
            Log::critical($message, $context);
        }
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
            $this->logCriticalThrottled('database', 'Health Check: Database connection failure: '.$e->getMessage());

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
                $this->logCriticalThrottled('queue_age', "Health Check Alert: Oldest queue job age ({$oldestAge}s) exceeded 120s limit!", [
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
            $this->logCriticalThrottled('queue_exception', 'Health Check: Queue check failed: '.$e->getMessage());

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
            // Fix #4: Use unix timestamp diff to ensure positive duration across Carbon 2 and Carbon 3
            $secondsSince = max(0, now()->getTimestamp() - $lastRunCarbon->getTimestamp());

            // Cron runs every minute; if > 180s (3 missed runs), report degraded
            $status = ($secondsSince > 180) ? 'degraded' : 'healthy';

            if ($secondsSince > 600) {
                $status = 'unhealthy';
                // Fix #12: Throttled logging to prevent log flooding during downtime
                $this->logCriticalThrottled('scheduler_lag', "Health Check Alert: Scheduled cron has not run in {$secondsSince} seconds!");
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

        // Fix #21: Measure account/app storage quota rather than host-wide 8.5TB partition
        $accountQuotaMb = config('services.health.account_storage_limit_mb');
        $effectiveTotal = (int) $totalBytes;
        $effectiveFree = (int) $freeBytes;

        if ($accountQuotaMb !== null && (int) $accountQuotaMb > 0) {
            $quotaBytes = (int) $accountQuotaMb * 1024 * 1024;
            $appUsageBytes = $this->getAppStorageUsageBytes();
            $effectiveTotal = $quotaBytes;
            $effectiveFree = min((int) $freeBytes, max(0, $quotaBytes - $appUsageBytes));
        } elseif ($totalBytes > (500 * 1024 * 1024 * 1024)) {
            // Server partition > 500GB (e.g. Hostinger 8.5TB node): default account quota 50GB
            $quotaBytes = 50 * 1024 * 1024 * 1024;
            $appUsageBytes = $this->getAppStorageUsageBytes();
            $effectiveTotal = $quotaBytes;
            $effectiveFree = min((int) $freeBytes, max(0, $quotaBytes - $appUsageBytes));
        }

        $freePercentage = round(($effectiveFree / max(1, $effectiveTotal)) * 100, 2);

        $status = 'healthy';
        if ($freePercentage < 5.0) {
            $status = 'unhealthy';
            $this->logCriticalThrottled('storage_full', "Health Check Alert: Critical disk storage space remaining ({$freePercentage}%)!");
        } elseif ($freePercentage < 15.0) {
            $status = 'degraded';
            Log::warning("Health Check Warning: Low disk storage space remaining ({$freePercentage}%).");
        }

        return [
            'status' => $status,
            'free_bytes' => $effectiveFree,
            'free_percentage' => $freePercentage,
        ];
    }

    /**
     * Cached measurement of application storage directory usage in bytes.
     */
    protected function getAppStorageUsageBytes(): int
    {
        return (int) Cache::remember('health_check_app_storage_bytes', 900, function (): int {
            $appDir = storage_path('app');
            if (! is_dir($appDir)) {
                return 0;
            }

            $size = 0;

            try {
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($appDir, \FilesystemIterator::SKIP_DOTS)
                );
                foreach ($iterator as $file) {
                    if ($file->isFile()) {
                        $size += $file->getSize();
                    }
                }
            } catch (Throwable) {
                // Return best-effort size
            }

            return $size;
        });
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
                $this->logCriticalThrottled('ledger_discrepancy', 'Health Check Alert: Ledger discrepancies detected during reconciliation!');
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
                if ($status !== 'unhealthy') {
                    $status = 'degraded';
                }
                $this->logCriticalThrottled('provisioning_lag', "Health Check Alert: {$unprovisionedCount} tenants stuck in provisioning for > 15 minutes!");
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
