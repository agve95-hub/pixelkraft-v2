<?php

namespace App\Console\Commands;

use App\Jobs\CheckUptimeJob;
use App\Models\Site;
use Illuminate\Console\Command;

/**
 * Dispatches one CheckUptimeJob per live site into the monitoring queue.
 *
 * Running HTTP checks inline blocked the scheduler process for up to
 * N_sites × 15 s (timeout) per run.  With 3 monitoring workers and per-site
 * jobs, checks run in parallel and finish well within the 5-minute window.
 * ShouldBeUnique on CheckUptimeJob prevents double-dispatch if the scheduler
 * fires before the previous round finishes.
 */
class CheckUptime extends Command
{
    protected $signature = 'platform:check-uptime';

    protected $description = 'Dispatch per-site uptime checks into the monitoring queue';

    public function handle(): int
    {
        $dispatched = 0;

        Site::where('is_active', true)
            ->whereNotNull('domain')
            ->where('deploy_status', 'live')
            ->select('id')
            ->chunkById(100, function ($sites) use (&$dispatched) {
                foreach ($sites as $site) {
                    CheckUptimeJob::dispatch($site->id);
                    $dispatched++;
                }
            });

        $this->info("Dispatched {$dispatched} uptime check job(s).");

        return self::SUCCESS;
    }
}
