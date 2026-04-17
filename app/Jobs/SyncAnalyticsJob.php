<?php

namespace App\Jobs;

use App\Models\Site;
use App\Services\AnalyticsAggregator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncAnalyticsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 600;

    public function __construct(
        public ?Site $site = null,
    ) {
        $this->onQueue('default');
    }

    public function handle(AnalyticsAggregator $aggregator): void
    {
        if ($this->site) {
            $aggregator->syncSite($this->site);

            return;
        }

        $aggregator->syncAll();
    }

    public function tags(): array
    {
        return $this->site
            ? ['analytics', "site:{$this->site->id}"]
            : ['analytics'];
    }
}
