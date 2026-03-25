<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Models\Site;
use App\Services\GitConflictException;
use App\Services\GitSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncFromWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 300;

    public function __construct(
        public Site $site,
        public array $payload = [],
    ) {
        $this->onQueue('git');
    }

    public function handle(GitSyncService $git): void
    {
        $commitMessage = $this->payload['head_commit']['message'] ?? 'Unknown commit';
        $pusher = $this->payload['pusher']['name'] ?? 'unknown';

        Log::info("SyncFromWebhookJob: pulling changes for [{$this->site->slug}]", [
            'pusher'  => $pusher,
            'message' => $commitMessage,
        ]);

        try {
            $hasChanges = $git->pull($this->site);

            if (! $hasChanges) {
                Log::info("SyncFromWebhookJob: no new changes for [{$this->site->slug}]");
                return;
            }

            Log::info("SyncFromWebhookJob: pulled new changes for [{$this->site->slug}]");

            // Re-parse affected pages
            ParseSiteJob::dispatch($this->site);

        } catch (GitConflictException $e) {
            Log::error("SyncFromWebhookJob: conflict for [{$this->site->slug}]", [
                'error' => $e->getMessage(),
            ]);

            Notification::createAlert(
                type: 'deploy_failed',
                title: "Git conflict on {$this->site->name}",
                body: "A push by {$pusher} conflicts with local edits. Manual resolution required.",
                siteId: $this->site->id,
                data: [
                    'pusher'  => $pusher,
                    'message' => $commitMessage,
                ],
            );

        } catch (\Throwable $e) {
            Log::error("SyncFromWebhookJob: failed for [{$this->site->slug}]", [
                'error' => $e->getMessage(),
            ]);

            Notification::createAlert(
                type: 'deploy_failed',
                title: "Sync failed for {$this->site->name}",
                body: $e->getMessage(),
                siteId: $this->site->id,
            );

            throw $e;
        }
    }

    public function tags(): array
    {
        return ['webhook-sync', "site:{$this->site->id}"];
    }
}
