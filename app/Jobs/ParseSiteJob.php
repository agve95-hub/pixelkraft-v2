<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Models\Site;
use App\Services\ParserService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ParseSiteJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 600;

    public int $uniqueFor = 600;

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

            // Dispatch per-page SEO analysis as individual jobs so this job
            // returns fast and doesn't monopolise the parsing queue workers.
            $this->site->pages()->select('id')->each(function ($page) {
                AnalyzeSeoJob::dispatch($page->id);
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

    public function uniqueId(): string
    {
        return $this->site->id;
    }

    public function tags(): array
    {
        return ['parse', "site:{$this->site->id}"];
    }
}
