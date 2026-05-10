<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Page;
use App\Models\Site;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

class BrokenLinkCrawler
{
    public function __construct(
        private SiteRuntimeService $runtime,
    ) {}

    /**
     * Crawl all pages of a site and find broken links.
     *
     * @return array{total_links: int, broken: array, redirects: array}
     */
    public function crawl(Site $site): array
    {
        $baseUrl = $site->domain ? "https://{$site->domain}" : null;
        $allBroken = [];
        $allRedirects = [];
        $totalLinks = 0;

        // Chunk pages to avoid loading thousands of Page models into memory at once.
        $site->pages()->where('is_published', true)->chunkById(100, function ($pages) use ($site, $baseUrl, &$allBroken, &$allRedirects, &$totalLinks) {
            foreach ($pages as $page) {
                $result = $this->crawlPage($site, $page, $baseUrl);
                $totalLinks += $result['total'];
                $allBroken = array_merge($allBroken, $result['broken']);
                $allRedirects = array_merge($allRedirects, $result['redirects']);
            }
        });

        if (! empty($allBroken)) {
            Notification::createAlert(
                type: 'broken_links',
                title: 'Found '.count($allBroken)." broken links on {$site->name}",
                body: 'Pages affected: '.count(array_unique(array_column($allBroken, 'page'))),
                siteId: $site->id,
                data: ['broken' => array_slice($allBroken, 0, 20)],
            );
        }

        Log::info("Link crawl for [{$site->slug}]: {$totalLinks} links, ".count($allBroken).' broken, '.count($allRedirects).' redirects');

        return [
            'total_links' => $totalLinks,
            'broken' => $allBroken,
            'redirects' => $allRedirects,
        ];
    }

    private function crawlPage(Site $site, Page $page, ?string $baseUrl): array
    {
        $repoPath = $site->repo_path;
        $filePath = "{$repoPath}/{$page->file_path}";

        if (! file_exists($filePath)) {
            return ['total' => 0, 'broken' => [], 'redirects' => []];
        }

        $html = file_get_contents($filePath);
        $crawler = new Crawler($html);

        $links = [];
        $broken = [];
        $redirects = [];

        // Extract all links
        $crawler->filter('a[href]')->each(function (Crawler $node) use (&$links) {
            $href = $node->attr('href');
            if ($href) {
                $links[] = $href;
            }
        });

        // Extract image sources
        $crawler->filter('img[src]')->each(function (Crawler $node) use (&$links) {
            $src = $node->attr('src');
            if ($src) {
                $links[] = $src;
            }
        });

        $externalLinks = [];

        foreach ($links as $link) {
            if (preg_match('/^(#|mailto:|tel:|javascript:)/', $link) || str_starts_with($link, 'data:')) {
                continue;
            }

            $resolvedUrl = $this->resolveUrl($link, $baseUrl, $page->url_path ?? '/');

            if (! $resolvedUrl) {
                continue;
            }

            if ($this->isInternalLink($link, $baseUrl)) {
                $internalPath = parse_url($resolvedUrl, PHP_URL_PATH) ?? '/';
                $exists = $site->pages()
                    ->where('url_path', $internalPath)
                    ->orWhere('url_path', rtrim($internalPath, '/'))
                    ->exists();

                if (! $exists && ! $this->fileExistsInRepo($site, $internalPath)) {
                    $broken[] = [
                        'url' => $link,
                        'page' => $page->url_path,
                        'type' => 'internal_404',
                        'status' => 404,
                    ];
                }

                continue;
            }

            if (str_starts_with($resolvedUrl, 'http')) {
                $externalLinks[$link] = $resolvedUrl;
            }
        }

        // Check external links in parallel batches of 10 to avoid sequential
        // HTTP round-trips (previously 1 request per link = up to N×timeout seconds).
        // Cap at 100 external links per page to keep the crawl bounded.
        $externalLinks = array_slice($externalLinks, 0, 100, true);
        $chunks = array_chunk($externalLinks, 10, true);

        foreach ($chunks as $chunk) {
            $results = $this->checkExternalLinksBatch($chunk);

            foreach ($results as $link => $result) {
                if ($result['status'] >= 400) {
                    $broken[] = [
                        'url' => $link,
                        'page' => $page->url_path,
                        'type' => 'external_'.$result['status'],
                        'status' => $result['status'],
                    ];
                } elseif ($result['status'] >= 300) {
                    $redirects[] = [
                        'url' => $link,
                        'page' => $page->url_path,
                        'status' => $result['status'],
                        'redirect_to' => $result['redirect_to'] ?? null,
                    ];
                }
            }
        }

        return [
            'total' => count($links),
            'broken' => $broken,
            'redirects' => $redirects,
        ];
    }

    private function resolveUrl(string $link, ?string $baseUrl, string $pagePath): ?string
    {
        if (str_starts_with($link, 'http://') || str_starts_with($link, 'https://')) {
            return $link;
        }

        if (! $baseUrl) {
            return null;
        }

        if (str_starts_with($link, '/')) {
            return $baseUrl.$link;
        }

        // Relative link
        $dir = dirname($pagePath);

        return $baseUrl.'/'.ltrim($dir.'/'.$link, '/');
    }

    private function isInternalLink(string $link, ?string $baseUrl): bool
    {
        if (str_starts_with($link, '/') && ! str_starts_with($link, '//')) {
            return true;
        }

        if ($baseUrl && str_starts_with($link, $baseUrl)) {
            return true;
        }

        return false;
    }

    private function fileExistsInRepo(Site $site, string $urlPath): bool
    {
        $repoPath = $site->repo_path;
        $basePaths = [];

        if ($this->runtime->usesRuntimeServer($site)) {
            $basePaths[] = "{$repoPath}/public";
            $basePaths[] = $repoPath;
        } else {
            $outputDir = $site->build_output_dir;
            $basePaths[] = $outputDir ? "{$repoPath}/{$outputDir}" : $repoPath;

            if (($outputDir ? "{$repoPath}/{$outputDir}" : $repoPath) !== $repoPath) {
                $basePaths[] = $repoPath;
            }
        }

        foreach (array_unique($basePaths) as $basePath) {
            $candidates = [
                "{$basePath}{$urlPath}",
                "{$basePath}{$urlPath}.html",
                "{$basePath}{$urlPath}/index.html",
            ];

            foreach ($candidates as $path) {
                if (file_exists($path)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check a batch of external URLs in parallel using Http::pool().
     *
     * Returns an array keyed by the original link text (not resolved URL) so
     * results can be matched back to the page source.
     *
     * @param  array<string, string>  $links  [link => resolvedUrl]
     * @return array<string, array{status: int, redirect_to: string|null}>
     */
    private function checkExternalLinksBatch(array $links): array
    {
        if (empty($links)) {
            return [];
        }

        $results = [];

        $responses = Http::pool(function (\Illuminate\Http\Client\Pool $pool) use ($links) {
            foreach ($links as $link => $url) {
                if (! $this->isPublicUrl($url)) {
                    continue;
                }

                $pool->as($link)
                    ->timeout(10)
                    ->withOptions(['allow_redirects' => false])
                    ->head($url);
            }
        });

        foreach ($links as $link => $url) {
            if (! $this->isPublicUrl($url)) {
                continue;
            }

            $response = $responses[$link] ?? null;

            if ($response instanceof \Throwable || $response === null) {
                $results[$link] = ['status' => 0, 'redirect_to' => null];

                continue;
            }

            $results[$link] = [
                'status' => $response->status(),
                'redirect_to' => $response->header('Location') ?: null,
            ];
        }

        return $results;
    }

    private function checkExternalLink(string $url): array
    {
        // SSRF guard: reject requests to private/loopback/link-local addresses.
        // Links in a site's HTML are user-controlled content; without this check
        // the crawler could be used as an SSRF relay to internal services.
        if (! $this->isPublicUrl($url)) {
            return ['status' => 0, 'redirect_to' => null];
        }

        try {
            $response = Http::timeout(10)
                ->withOptions(['allow_redirects' => false])
                ->head($url);

            $status = $response->status();
            $redirectTo = $response->header('Location');

            return ['status' => $status, 'redirect_to' => $redirectTo];
        } catch (\Throwable $e) {
            return ['status' => 0, 'redirect_to' => null];
        }
    }

    /**
     * Return true only if the URL resolves to a publicly routable IP address.
     * Rejects localhost, RFC 1918 ranges, link-local (169.254.x.x), and non-HTTP schemes.
     */
    private function isPublicUrl(string $url): bool
    {
        $parsed = parse_url($url);
        $scheme = strtolower($parsed['scheme'] ?? '');

        if (! in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        $host = $parsed['host'] ?? '';

        if ($host === '') {
            return false;
        }

        // Resolve hostname to IP (or use raw IP directly).
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ip = $host;
        } else {
            $resolved = gethostbyname($host);
            if ($resolved === $host) {
                // Hostname could not be resolved — skip to avoid hanging.
                return false;
            }
            $ip = $resolved;
        }

        return (bool) filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }
}
