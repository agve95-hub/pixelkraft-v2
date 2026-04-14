<?php

namespace App\Console\Commands;

use App\Jobs\DeploySiteJob;
use App\Models\BlogPost;
use Illuminate\Console\Command;

class PublishScheduled extends Command
{
    protected $signature = 'pixelkraft:publish-scheduled';

    protected $description = 'Publish blog posts that are scheduled for the current time';

    public function handle(): int
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
            $post->update([
                'status' => 'published',
                'published_at' => now(),
            ]);

            $this->info("Published: {$post->title}");

            // Track unique sites that need redeployment
            $sitesToDeploy->put($post->site_id, $post->site);
        }

        // Trigger deploy for each affected site
        foreach ($sitesToDeploy as $site) {
            DeploySiteJob::dispatch($site, 'schedule');
            $this->info("Deploy triggered for: {$site->name}");
        }

        $this->info("Published {$posts->count()} posts, triggered {$sitesToDeploy->count()} deploys.");

        return self::SUCCESS;
    }
}
