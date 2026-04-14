<?php

namespace App\Console\Commands;

use App\Models\Page;
use App\Models\Site;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Fetch Lighthouse-equivalent scores from the Google PageSpeed Insights API.
 *
 * Unlike running the Lighthouse CLI, this approach:
 *  - Requires no Chromium / headless-Chrome on the server.
 *  - Uses Google's own infrastructure to audit pages from the public internet.
 *  - Requires a free Google Cloud API key (PSI_API_KEY in .env).
 *    Without a key, the API still works but is capped at ~25 req/100 sec.
 *
 * PSI v5 docs: https://developers.google.com/speed/docs/insights/v5/reference/pagespeedapi/runpagespeed
 */
class RunLighthouse extends Command
{
    protected $signature = 'pixelkraft:run-lighthouse {--site= : Specific site slug} {--strategy=mobile : mobile or desktop}';
    protected $description = 'Fetch PageSpeed Insights scores for all live site pages';

    private const PSI_ENDPOINT = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';

    public function handle(): int
    {
        $strategy = in_array($this->option('strategy'), ['mobile', 'desktop'], true)
            ? $this->option('strategy')
            : 'mobile';

        $query = Site::where('is_active', true)
            ->where('deploy_status', 'live')
            ->whereNotNull('domain');

        if ($slug = $this->option('site')) {
            $query->where('slug', $slug);
        }

        $sites = $query->get();

        foreach ($sites as $site) {
            $this->info("Running PageSpeed Insights for [{$site->name}]...");

            $pages = $site->pages()
                ->where('is_published', true)
                ->limit(10)
                ->get();

            foreach ($pages as $page) {
                $scores = $this->auditPage($site, $page, $strategy);

                if ($scores) {
                    $page->update(['lighthouse_score' => $scores]);
                    $this->info(
                        "  {$page->url_path}: " .
                        "perf={$scores['performance']}, " .
                        "a11y={$scores['accessibility']}, " .
                        "bp={$scores['best_practices']}, " .
                        "seo={$scores['seo']}"
                    );
                }
            }
        }

        return self::SUCCESS;
    }

    private function auditPage(Site $site, Page $page, string $strategy): ?array
    {
        $url = 'https://' . $site->domain . ($page->url_path ?? '/');
        $apiKey = config('pixelkraft.psi_api_key');

        $params = [
            'url'      => $url,
            'strategy' => $strategy,
            'category' => ['performance', 'accessibility', 'best-practices', 'seo'],
        ];

        if ($apiKey) {
            $params['key'] = $apiKey;
        }

        try {
            $response = Http::timeout(60)
                ->retry(2, 5000)
                ->get(self::PSI_ENDPOINT, $params);

            if (! $response->successful()) {
                Log::warning("PageSpeed Insights request failed for [{$url}]", [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);

                return null;
            }

            $data = $response->json();
            $categories = $data['lighthouseResult']['categories'] ?? [];

            return [
                'performance'    => $this->scoreToInt($categories['performance']['score'] ?? null),
                'accessibility'  => $this->scoreToInt($categories['accessibility']['score'] ?? null),
                'best_practices' => $this->scoreToInt($categories['best-practices']['score'] ?? null),
                'seo'            => $this->scoreToInt($categories['seo']['score'] ?? null),
            ];
        } catch (\Throwable $e) {
            Log::warning("PageSpeed Insights error for [{$url}]", ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function scoreToInt(mixed $score): ?int
    {
        if ($score === null) {
            return null;
        }

        // PSI scores are floats in [0, 1]; multiply by 100 for integer percent.
        return (int) round((float) $score * 100);
    }
}
