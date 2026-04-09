<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Models\Page;
use App\Models\Site;
use App\Services\ParserService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ParseSiteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 600;

    public function __construct(
        public Site $site,
    ) {
        $this->onQueue('parsing');
    }

    public function handle(ParserService $parser): void
    {
        Log::info("ParseSiteJob started for [{$this->site->slug}]");

        try {
            $pageCount = $parser->parseSite($this->site);

            Log::info("ParseSiteJob completed for [{$this->site->slug}]: {$pageCount} pages");

            $this->site->load('pages');

            $this->site->pages->each(function (Page $page) {
                $page->update(['seo_score' => $this->computeSeoScore($page)]);
            });

        } catch (\Throwable $e) {
            Log::error("ParseSiteJob failed for [{$this->site->slug}]", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            Notification::createAlert(
                type: 'deploy_failed',
                title: "Parsing failed for {$this->site->name}",
                body: $e->getMessage(),
                siteId: $this->site->id,
            );

            throw $e;
        }
    }

    /**
     * Compute a basic SEO score (0-100) for a page.
     */
    private function computeSeoScore(Page $page): int
    {
        $score = 0;

        // Title (25 points)
        if ($page->title) {
            $len = mb_strlen($page->title);
            $score += ($len >= 10 && $len <= 70) ? 25 : 15;
        }

        // Meta description (20 points)
        if ($page->meta_description) {
            $len = mb_strlen($page->meta_description);
            $score += ($len >= 50 && $len <= 160) ? 20 : 10;
        }

        // Open Graph (15 points)
        if ($page->og_title) $score += 5;
        if ($page->og_description) $score += 5;
        if ($page->og_image) $score += 5;

        // Canonical URL (10 points)
        if ($page->canonical_url) $score += 10;

        // Schema.org JSON-LD (10 points)
        if ($page->schema_json) $score += 10;

        // Content exists (10 points)
        if ($page->content_hash) $score += 10;

        // Has URL path (10 points)
        if ($page->url_path && $page->url_path !== '/') $score += 10;

        return min(100, $score);
    }

    public function tags(): array
    {
        return ['parse', "site:{$this->site->id}"];
    }
}
