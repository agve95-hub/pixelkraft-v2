<?php

namespace App\Jobs;

use App\Models\Site;
use App\Services\DeployService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;

class DeploySiteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(
        public Site $site,
        public string $triggeredBy = 'manual',
    ) {
        $this->onQueue('default');
    }

    public function handle(DeployService $deployer): void
    {
        $site = $this->site->exists ? $this->site->fresh() : $this->site;

        ['log' => $log, 'release' => $release] = $deployer->beginDeployment($site, $this->triggeredBy);

        Bus::chain([
            new ProvisionEnvironmentJob($this->site->id, $log->id, $release->id),
            new BuildSiteJob($this->site->id, $log->id, $release->id),
            new InjectTrackingJob($this->site->id, $log->id, $release->id),
            new ActivateReleaseJob($this->site->id, $log->id, $release->id),
        ])->onQueue('default')->dispatch();
    }

    public function tags(): array
    {
        return ['deploy', "site:{$this->site->id}"];
    }
}
