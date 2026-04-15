<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Models\Site;
use App\Services\GitSyncService;
use App\Services\ProjectDetector;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CloneRepoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public int $timeout = 300;

    public function __construct(
        public Site $site,
    ) {
        $this->onQueue('git');
    }

    public function handle(GitSyncService $git, ProjectDetector $detector): void
    {
        Log::info("CloneRepoJob started for [{$this->site->slug}]");

        try {
            // Clone the repository
            $git->cloneRepo($this->site);

            // Auto-detect project type
            $detection = $detector->applyToSite($this->site);

            Log::info("CloneRepoJob completed for [{$this->site->slug}]", [
                'project_type' => $detection['type'],
                'confidence' => $detection['confidence'],
            ]);

            // Dispatch site parsing job (Phase 2)
            ParseSiteJob::dispatch($this->site);

        } catch (\Throwable $e) {
            Log::error("CloneRepoJob failed for [{$this->site->slug}]", [
                'error' => $e->getMessage(),
            ]);

            Notification::createAlert(
                type: 'deploy_failed',
                title: "Failed to clone {$this->site->name}",
                body: self::scrubGitMessage($e->getMessage()),
                siteId: $this->site->id,
            );

            throw $e;
        }
    }

    public function tags(): array
    {
        return ['clone', "site:{$this->site->id}"];
    }

    /**
     * Strip embedded git tokens from error messages before they are stored in
     * the notifications table and surfaced in the dashboard.
     * Mirrors the same pattern used in GitSyncService::scrubSecrets().
     */
    private static function scrubGitMessage(string $message): string
    {
        return preg_replace('/x-access-token:[^@\s]+@/', 'x-access-token:[REDACTED]@', $message) ?? $message;
    }
}
