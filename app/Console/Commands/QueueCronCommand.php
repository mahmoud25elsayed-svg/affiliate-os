<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

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
