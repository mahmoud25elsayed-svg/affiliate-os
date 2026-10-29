<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class QueueCronCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'queue:cron
                            {--queue=finance,notifications,courier,default : Priority-ordered comma-separated list of queues}
                            {--stop-when-empty : Stop when all queues are empty}
                            {--max-time=55 : Maximum seconds worker should run to avoid cron overlap}
                            {--memory=128 : Memory limit in megabytes for shared hosting}
                            {--tries=3 : The number of times to attempt a job before failing}
                            {--sleep=1 : Number of seconds to sleep when no job is available}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process queued jobs with strict business domain priorities on shared hosting cron';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $queueOrder = (string) $this->option('queue');

        // Fix #15: Dynamically discover and append any active queues in jobs table to prevent stuck jobs
        if ($queueOrder === 'finance,notifications,courier,default') {
            try {
                /** @var array<string> $extraQueues */
                $extraQueues = DB::table('jobs')->distinct()->pluck('queue')->filter()->all();
                $queues = array_values(array_unique(array_merge(['finance', 'notifications', 'courier', 'default'], $extraQueues)));
                $queueOrder = implode(',', $queues);
            } catch (\Throwable) {
                // Fallback to configured order
            }
        }

        $maxTime = (int) $this->option('max-time');
        $memory = (int) $this->option('memory');
        $tries = (int) $this->option('tries');
        $sleep = (int) $this->option('sleep');
        $stopWhenEmpty = (bool) $this->option('stop-when-empty');

        $this->info("Starting AffiliateOS Priority Queue Worker [{$queueOrder}] (max-time: {$maxTime}s, mem: {$memory}MB)...");

        $params = [
            '--queue' => $queueOrder,
            '--max-time' => $maxTime,
            '--memory' => $memory,
            '--tries' => $tries,
            '--sleep' => $sleep,
        ];

        if ($stopWhenEmpty) {
            $params['--stop-when-empty'] = true;
        }

        $exitCode = $this->call('queue:work', $params);

        // Exit code 12 is Worker::EXIT_TIMEOUT (normal expected shutdown when max-time is reached)
        if ($exitCode === 12) {
            return self::SUCCESS;
        }

        return $exitCode;
    }
}
