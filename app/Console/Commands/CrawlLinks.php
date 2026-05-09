<?php

namespace App\Console\Commands;

use App\Models\Site;
use App\Services\BrokenLinkCrawler;
use Illuminate\Console\Command;

class CrawlLinks extends Command
{
    protected $signature = 'platform:crawl-links {--site= : Specific site slug}';

    protected $description = 'Crawl sites for broken links';

    public function handle(BrokenLinkCrawler $crawler): int
    {
        $query = Site::where('is_active', true)->whereNotNull('domain');

        if ($slug = $this->option('site')) {
            $query->where('slug', $slug);
        }

        $sites = $query->get();

        foreach ($sites as $site) {
            $this->info("Crawling [{$site->name}]...");

            $result = $crawler->crawl($site);

            $this->info("  Links: {$result['total_links']}, Broken: ".count($result['broken']).', Redirects: '.count($result['redirects']));
        }

        return self::SUCCESS;
    }
}
