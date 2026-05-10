<?php

namespace App\Jobs;

use App\Enums\DeployStatus;
use App\Models\Site;
use App\Services\DeployService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;

class DeploySiteJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public int $uniqueFor = 3600;

    public function __construct(
        public Site $site,
        public string $triggeredBy = 'manual',
    ) {
        $this->onQueue('deploy');
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
        ])->onQueue('deploy')->dispatch();
    }

    public function uniqueId(): string
    {
        return (string) ($this->site->id ?? '');
    }

    public function failed(?\Throwable $exception): void
    {
        $site = Site::query()->find($this->site->id ?? null);
        if ($site) {
            $site->update(['deploy_status' => DeployStatus::Failed]);
        }
    }

    public function tags(): array
    {
        return ['deploy', "site:{$this->site->id}"];
    }
}
