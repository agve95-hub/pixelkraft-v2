<?php

namespace App\Jobs;

use App\Models\Site;
use App\Services\SslService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProvisionSslJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $backoff = 60;

    public int $timeout = 180;

    public function __construct(
        public Site $site,
    ) {
        $this->onQueue('deploy');
    }

    public function handle(SslService $ssl): void
    {
        $ssl->provision($this->site);
    }

    public function tags(): array
    {
        return ['ssl', "site:{$this->site->id}"];
    }
}
