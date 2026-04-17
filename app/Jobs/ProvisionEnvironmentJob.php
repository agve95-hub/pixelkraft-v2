<?php

namespace App\Jobs;

use App\Models\DeployLog;
use App\Models\DeploymentRelease;
use App\Models\Site;
use App\Services\DeployService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProvisionEnvironmentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 180;

    public function __construct(
        public string $siteId,
        public string $deployLogId,
        public string $releaseId,
    ) {
        $this->onQueue('default');
    }

    public function handle(DeployService $deployer): void
    {
        $deployer->provisionEnvironment($this->site(), $this->log(), $this->release());
    }

    public function failed(?Throwable $exception): void
    {
        app(DeployService::class)->markDeploymentFailed($this->siteId, $this->deployLogId, $this->releaseId, $exception ?: 'Provisioning failed.');
    }

    public function tags(): array
    {
        return ['deploy', "site:{$this->siteId}", 'stage:provision'];
    }

    private function site(): Site
    {
        return Site::query()->findOrFail($this->siteId);
    }

    private function log(): DeployLog
    {
        return DeployLog::query()->findOrFail($this->deployLogId);
    }

    private function release(): DeploymentRelease
    {
        return DeploymentRelease::query()->findOrFail($this->releaseId);
    }
}
