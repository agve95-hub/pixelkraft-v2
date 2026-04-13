<?php

namespace App\Jobs;

use App\Jobs\DeploySiteJob;
use App\Models\Notification;
use App\Models\Site;
use App\Models\WebhookDelivery;
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
        public ?string $deliveryId = null,
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
                $this->markDeliveryProcessed('ignored');
                Log::info("SyncFromWebhookJob: no new changes for [{$this->site->slug}]");
                return;
            }

            Log::info("SyncFromWebhookJob: pulled new changes for [{$this->site->slug}]");

            // Re-parse affected pages
            ParseSiteJob::dispatch($this->site);

            if ($this->site->deploy_on_webhook) {
                DeploySiteJob::dispatch($this->site, 'webhook');
                Log::info("SyncFromWebhookJob: dispatched deploy for [{$this->site->slug}]");
            }

            $this->markDeliveryProcessed('processed');

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
            $this->markDeliveryProcessed('conflict');

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
            $this->markDeliveryProcessed('failed');

            throw $e;
        }
    }

    public function tags(): array
    {
        return ['webhook-sync', "site:{$this->site->id}"];
    }

    private function markDeliveryProcessed(string $status): void
    {
        if (! $this->deliveryId) {
            return;
        }

        WebhookDelivery::query()
            ->where('provider', 'github')
            ->where('delivery_id', $this->deliveryId)
            ->update([
                'site_id' => $this->site->id,
                'status' => $status,
                'processed_at' => now(),
            ]);
    }
}
