<?php

namespace App\Jobs;

use App\Models\Page;
use App\Services\SeoAnalyzer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Runs SEO analysis for a single page.
 *
 * Dispatched per-page from ParseSiteJob instead of running all pages
 * synchronously in one long-running job.  This allows the parsing queue
 * to process page discovery fast while SEO analysis runs in parallel.
 */
class AnalyzeSeoJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 30;

    public int $uniqueFor = 120;

    public function __construct(public string $pageId)
    {
        $this->onQueue('parsing');
    }

    public function uniqueId(): string
    {
        return $this->pageId;
    }

    public function handle(SeoAnalyzer $analyzer): void
    {
        $page = Page::query()->with('site')->find($this->pageId);

        if ($page) {
            $analyzer->analyze($page);
        }
    }

    public function tags(): array
    {
        return ['seo', "page:{$this->pageId}"];
    }
}
