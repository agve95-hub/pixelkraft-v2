<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Models\Site;
use App\Services\ParserService;
use App\Services\SeoAnalyzer;
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
        $this->onQueue('default');
    }

    public function handle(ParserService $parser): void
    {
        Log::info("ParseSiteJob started for [{$this->site->slug}]");

        try {
            $pageCount = $parser->parseSite($this->site);

            Log::info("ParseSiteJob completed for [{$this->site->slug}]: {$pageCount} pages");

            $this->site->load('pages');

            $analyzer = app(SeoAnalyzer::class);
            foreach ($this->site->pages as $page) {
                $analyzer->analyze($page->fresh());
            }

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

    public function tags(): array
    {
        return ['parse', "site:{$this->site->id}"];
    }
}
