<?php

namespace App\Jobs;

use App\Models\Site;
use App\Services\DeployService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DeploySiteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 600;

    public function __construct(
        public Site $site,
        public string $triggeredBy = 'manual',
    ) {
        $this->onQueue('deploy');
    }

    public function handle(DeployService $deployer): void
    {
        $deployer->deploy($this->site, $this->triggeredBy);
    }

    public function tags(): array
    {
        return ['deploy', "site:{$this->site->id}"];
    }
}
