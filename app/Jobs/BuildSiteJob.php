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

class BuildSiteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 600;

    public int $backoff = 30;

    public function __construct(
        public string $siteId,
        public string $deployLogId,
        public string $releaseId,
    ) {
        $this->onQueue('deploy');
    }

    public function handle(DeployService $deployer): void
    {
        $deployer->buildSite($this->site(), $this->log(), $this->release());
    }

    public function failed(?Throwable $exception): void
    {
        app(DeployService::class)->markDeploymentFailed($this->siteId, $this->deployLogId, $this->releaseId, $exception ?: 'Build failed.');
    }

    public function tags(): array
    {
        return ['deploy', "site:{$this->siteId}", 'stage:build'];
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
