<?php

use App\Domain\CallCenter\Jobs\ProcessExhaustedCallAttemptsJob;
use App\Domain\CallCenter\Jobs\ReleaseExpiredOrderLocksJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('horizon:snapshot')->everyFiveMinutes();
Schedule::command('affiliate:calculate-daily-quality-scores')->dailyAt('00:05');
Schedule::command('reports:aggregate-daily')->dailyAt('01:00');
Schedule::job(new ReleaseExpiredOrderLocksJob)->everyFiveMinutes();
Schedule::job(new ProcessExhaustedCallAttemptsJob)->hourly();
