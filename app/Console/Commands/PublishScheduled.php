<?php

namespace App\Console\Commands;

use App\Enums\BlogPostStatus;
use App\Models\BlogPost;
use App\Services\BlogPostPublisher;
use App\Services\DeployDispatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PublishScheduled extends Command
{
    protected $signature = 'pixelkraft:publish-scheduled';

    protected $description = 'Publish blog posts that are scheduled for the current time';

    public function handle(BlogPostPublisher $publisher, DeployDispatcher $deploys): int
    {
        $posts = BlogPost::where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->with('site')
            ->get();

        if ($posts->isEmpty()) {
            return self::SUCCESS;
        }

        $sitesToDeploy = collect();

        foreach ($posts as $post) {
            $site = $post->site;
            if (! $site) {
                $this->warn("Skipping post {$post->id}: site missing.");

                continue;
            }

            try {
                DB::transaction(function () use ($post, $site, $publisher): void {
                    $post->transitionStatus(BlogPostStatus::Published);
                    $post->update(['published_at' => now()]);

                    $publisher->writeToRepository(
                        $site,
                        $post->fresh(),
                        "Schedule publish: {$post->title}"
                    );
                });
            } catch (\Throwable $e) {
                $publisher->logRepositoryFailure($site, $post, $e);
                $this->error("Failed to publish {$post->title}: {$e->getMessage()}");

                continue;
            }

            $this->info("Published: {$post->title}");

            $sitesToDeploy->put($site->id, $site);
        }

        foreach ($sitesToDeploy as $site) {
            if ($deploys->dispatch($site, 'schedule')) {
                $this->info("Deploy triggered for: {$site->name}");
            } else {
                $this->warn("Deploy already in progress for: {$site->name}");
            }
        }

        $this->info("Processed {$posts->count()} scheduled post(s), triggered {$sitesToDeploy->count()} deploy(s).");

        return self::SUCCESS;
    }
}
