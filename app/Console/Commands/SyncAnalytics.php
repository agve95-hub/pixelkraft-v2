<?php

namespace App\Console\Commands;

use App\Services\AnalyticsAggregator;
use Illuminate\Console\Command;

class SyncAnalytics extends Command
{
    protected $signature = 'pixelkraft:sync-analytics';
    protected $description = 'Sync analytics (GA4 organic SEO + Cloudflare) into snapshots';

    public function handle(AnalyticsAggregator $aggregator): int
    {
        $synced = $aggregator->syncAll();

        $this->info("Analytics sync completed ({$synced} write operations).");

        return self::SUCCESS;
    }
}
