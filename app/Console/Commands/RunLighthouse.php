<?php

namespace App\Console\Commands;

use App\Models\Page;
use App\Models\Site;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class RunLighthouse extends Command
{
    protected $signature = 'pixelkraft:run-lighthouse {--site= : Specific site slug}';
    protected $description = 'Run Lighthouse audits on all live site pages';

    public function handle(): int
    {
        $query = Site::where('is_active', true)
            ->where('deploy_status', 'live')
            ->whereNotNull('domain');

        if ($slug = $this->option('site')) {
            $query->where('slug', $slug);
        }

        $sites = $query->get();

        foreach ($sites as $site) {
            $this->info("Running Lighthouse for [{$site->name}]...");

            $pages = $site->pages()
                ->where('is_published', true)
                ->limit(10) // Limit to avoid overwhelming the VPS
                ->get();

            foreach ($pages as $page) {
                $scores = $this->auditPage($site, $page);

                if ($scores) {
                    $page->update(['lighthouse_score' => $scores]);
                    $this->info("  {$page->url_path}: perf={$scores['performance']}, a11y={$scores['accessibility']}, bp={$scores['best_practices']}, seo={$scores['seo']}");
                }
            }
        }

        return self::SUCCESS;
    }

    private function auditPage(Site $site, Page $page): ?array
    {
        $url = "https://{$site->domain}" . ($page->url_path ?? '/');

        // Use lighthouse CLI if available
        $result = Process::timeout(60)->run(
            'lighthouse ' . escapeshellarg($url) .
            ' --output=json --quiet --chrome-flags="--headless --no-sandbox" 2>/dev/null'
        );

        if ($result->successful()) {
            $data = json_decode($result->output(), true);

            if ($data && isset($data['categories'])) {
                return [
                    'performance'    => (int) (($data['categories']['performance']['score'] ?? 0) * 100),
                    'accessibility'  => (int) (($data['categories']['accessibility']['score'] ?? 0) * 100),
                    'best_practices' => (int) (($data['categories']['best-practices']['score'] ?? 0) * 100),
                    'seo'            => (int) (($data['categories']['seo']['score'] ?? 0) * 100),
                ];
            }
        }

        // Fallback: basic performance check via HTTP timing
        try {
            $start = microtime(true);
            $response = \Illuminate\Support\Facades\Http::timeout(15)->get($url);
            $loadTime = (microtime(true) - $start) * 1000;

            if ($response->successful()) {
                $perfScore = match (true) {
                    $loadTime < 1000 => 95,
                    $loadTime < 2000 => 80,
                    $loadTime < 3000 => 60,
                    $loadTime < 5000 => 40,
                    default          => 20,
                };

                return [
                    'performance'    => $perfScore,
                    'accessibility'  => null,
                    'best_practices' => null,
                    'seo'            => $page->seo_score,
                ];
            }
        } catch (\Throwable $e) {
            Log::warning("Lighthouse fallback failed for [{$url}]", ['error' => $e->getMessage()]);
        }

        return null;
    }
}
