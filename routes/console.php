<?php

use App\Domain\CallCenter\Jobs\ProcessExhaustedCallAttemptsJob;
use App\Domain\CallCenter\Jobs\ReleaseExpiredOrderLocksJob;
use App\Jobs\Subscriptions\CheckExpiringSubscriptions;
use App\Jobs\Subscriptions\ProcessPastDueSubscriptions;
use App\Jobs\Subscriptions\SuspendDelinquentSubscriptions;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;
use Laravel\Horizon\Horizon;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Scheduler heartbeat pulse for /health monitoring
Schedule::call(function () {
    Cache::forever('system:last_scheduler_run', now()->toIso8601String());
})->everyMinute()->name('scheduler-heartbeat');

// Only run horizon snapshot when using redis and Horizon is installed (Fix #5)
if (config('queue.default') === 'redis' && class_exists(Horizon::class)) {
    Schedule::command('horizon:snapshot')->everyFiveMinutes();
}

Schedule::command('affiliate:calculate-daily-quality-scores')->dailyAt('00:05');
Schedule::command('reports:aggregate-daily')->dailyAt('01:00');
Schedule::command('ledger:reconcile')->dailyAt('02:00');
Schedule::command('affiliate:settle-commissions')->dailyAt('02:30');
Schedule::command('inventory:reconcile')->dailyAt('03:30');
Schedule::command('affiliate:prune-clicks')->dailyAt('04:00');
Schedule::command('compliance:prune-expired-data')->dailyAt('04:30');
Schedule::command('promotions:scan-abandoned-carts')->hourly();

// Backup database with optional remote disk (Fix #6)
$backupCmd = 'app:backup-database';
if ($backupDisk = env('BACKUP_DISK')) {
    $backupCmd .= " --disk={$backupDisk}";
}
Schedule::command($backupCmd)->dailyAt('03:00');

// Queue runner with 10-minute mutex TTL (Fix #8: avoid 24h worker lockouts)
Schedule::command('queue:cron --stop-when-empty')->everyMinute()->withoutOverlapping(10);
Schedule::job(new ReleaseExpiredOrderLocksJob)->everyFiveMinutes();
Schedule::job(new ProcessExhaustedCallAttemptsJob)->hourly();

// Subscription life cycle automations (BL-03)
Schedule::job(new CheckExpiringSubscriptions)->dailyAt('08:00');
Schedule::job(new ProcessPastDueSubscriptions)->dailyAt('08:30');
Schedule::job(new SuspendDelinquentSubscriptions)->dailyAt('09:00');
