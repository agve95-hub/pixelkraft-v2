<?php

namespace App\Console\Commands;

use App\Models\Site;
use App\Services\SeoAnalyzer;
use Illuminate\Console\Command;

class AnalyzeSeo extends Command
{
    protected $signature = 'platform:analyze-seo
                            {--site= : Site slug or UUID to limit analysis}
                            {--all : Analyze every active site (default when --site omitted)}';

    protected $description = 'Run SeoAnalyzer on pages to refresh seo_score and persist seo_issues rows';

    public function handle(SeoAnalyzer $analyzer): int
    {
        $siteOption = $this->option('site');
        $all = (bool) $this->option('all');

        if ($siteOption) {
            $site = Site::query()
                ->where(function ($q) use ($siteOption) {
                    $q->where('slug', $siteOption)->orWhere('id', $siteOption);
                })
                ->first();

            if (! $site) {
                $this->error("No site found for slug or id: {$siteOption}");

                return self::FAILURE;
            }

            $this->analyzeSite($analyzer, $site);

            return self::SUCCESS;
        }

        if (! $all) {
            $this->warn('Pass --site=slug-or-uuid or --all to analyze sites.');

            return self::FAILURE;
        }

        $sites = Site::query()->where('is_active', true)->orderBy('name')->get();

        if ($sites->isEmpty()) {
            $this->info('No active sites.');

            return self::SUCCESS;
        }

        foreach ($sites as $site) {
            $this->analyzeSite($analyzer, $site);
        }

        return self::SUCCESS;
    }

    private function analyzeSite(SeoAnalyzer $analyzer, Site $site): void
    {
        $site->load('pages');
        $count = $site->pages->count();

        if ($count === 0) {
            $this->line("  [{$site->slug}] no pages, skipped");

            return;
        }

        $this->info("Analyzing {$count} page(s) for {$site->name} ({$site->slug})…");

        foreach ($site->pages as $page) {
            try {
                $analyzer->analyze($page); // pages already loaded above — no ->fresh() N+1
            } catch (\Throwable $e) {
                $this->error("  Failed page {$page->id}: {$e->getMessage()}");
            }
        }

        $this->line('  Done.');
    }
}
