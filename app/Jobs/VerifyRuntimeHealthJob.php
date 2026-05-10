<?php

namespace App\Jobs;

use App\Models\DeployLog;
use App\Models\Notification;
use App\Models\Site;
use App\Services\SiteRuntimeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Verifies that a runtime site (Next.js, Nuxt) is responding after deploy.
 *
 * Dispatched from ActivateReleaseJob instead of blocking the worker thread
 * inside SiteRuntimeService::waitUntilHealthy().  Retries every 5 seconds
 * for up to 2 minutes before alerting the operator.
 */
class VerifyRuntimeHealthJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 24;

    public int $backoff = 5;

    public int $timeout = 15;

    public function __construct(
        public string $siteId,
        public string $deployLogId,
        public int $port,
    ) {
        $this->onQueue('monitoring');
    }

    public function handle(SiteRuntimeService $runtime): void
    {
        $site = Site::query()->find($this->siteId);

        if (! $site) {
            return;
        }

        if ($runtime->isReachable($site)) {
            $this->appendLog("  Runtime health check passed on port {$this->port}.");
            Log::info("VerifyRuntimeHealthJob: site [{$site->slug}] is healthy on port {$this->port}");

            return;
        }

        // Not yet healthy — release back to the queue for another attempt.
        $this->release(5);
    }

    public function failed(?\Throwable $exception): void
    {
        $site = Site::query()->find($this->siteId);
        $siteName = $site?->name ?? 'unknown site';

        $this->appendLog("  WARNING: Runtime server on port {$this->port} never responded after deploy.");

        Notification::createAlert(
            type: 'deploy_failed',
            title: "Runtime health check failed for {$siteName}",
            body: "The Node.js server on port {$this->port} did not respond within 2 minutes after deploy. Check the runtime log in the dashboard.",
            siteId: $site?->id,  // null when site is deleted between dispatch and failure
        );
    }

    public function tags(): array
    {
        return ['runtime-health', "site:{$this->siteId}"];
    }

    private function appendLog(string $line): void
    {
        $log = DeployLog::query()->find($this->deployLogId);
        $log?->appendLog($line);
    }
}
