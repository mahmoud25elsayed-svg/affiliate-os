<?php

use App\Domain\CallCenter\Jobs\ProcessExhaustedCallAttemptsJob;
use App\Domain\CallCenter\Jobs\ReleaseExpiredOrderLocksJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Scheduler heartbeat pulse for /health monitoring
Schedule::call(function () {
    Cache::forever('system:last_scheduler_run', now()->toIso8601String());
})->everyMinute()->name('scheduler-heartbeat');

Schedule::command('horizon:snapshot')->everyFiveMinutes();
Schedule::command('affiliate:calculate-daily-quality-scores')->dailyAt('00:05');
Schedule::command('reports:aggregate-daily')->dailyAt('01:00');
Schedule::command('ledger:reconcile')->dailyAt('02:00');
Schedule::command('app:backup-database')->dailyAt('03:00');
Schedule::command('compliance:prune-expired-data')->dailyAt('04:00');
Schedule::command('queue:cron --stop-when-empty')->everyMinute()->withoutOverlapping();
Schedule::job(new ReleaseExpiredOrderLocksJob)->everyFiveMinutes();
Schedule::job(new ProcessExhaustedCallAttemptsJob)->hourly();
