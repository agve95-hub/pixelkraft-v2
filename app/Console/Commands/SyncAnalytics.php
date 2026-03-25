<?php

namespace App\Console\Commands;

use App\Services\AnalyticsAggregator;
use Illuminate\Console\Command;

class SyncAnalytics extends Command
{
    protected $signature = 'pixelkraft:sync-analytics';
    protected $description = 'Sync analytics data from Google Analytics and Cloudflare';

    public function handle(AnalyticsAggregator $aggregator): int
    {
        $synced = $aggregator->syncAll();

        $this->info("Analytics synced: {$synced} snapshots.");

        return self::SUCCESS;
    }
}
