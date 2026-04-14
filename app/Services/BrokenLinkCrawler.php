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
        $pages = $site->pages()->where('is_published', true)->get();
        $baseUrl = $site->domain ? "https://{$site->domain}" : null;
        $allBroken = [];
        $allRedirects = [];
        $totalLinks = 0;

        foreach ($pages as $page) {
            $result = $this->crawlPage($site, $page, $baseUrl);
            $totalLinks += $result['total'];
            $allBroken = array_merge($allBroken, $result['broken']);
            $allRedirects = array_merge($allRedirects, $result['redirects']);
        }

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

        foreach ($links as $link) {
            // Skip anchors, mailto, tel, javascript
            if (preg_match('/^(#|mailto:|tel:|javascript:)/', $link)) {
                continue;
            }

            // Skip data URIs
            if (str_starts_with($link, 'data:')) {
                continue;
            }

            // Resolve relative URLs
            $resolvedUrl = $this->resolveUrl($link, $baseUrl, $page->url_path ?? '/');

            if (! $resolvedUrl) {
                continue;
            }

            // Check if it's an internal link that should exist as a page
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

            // Check external links (with timeout and rate limiting)
            if (str_starts_with($resolvedUrl, 'http')) {
                $result = $this->checkExternalLink($resolvedUrl);

                if ($result['status'] >= 400) {
                    $broken[] = [
                        'url' => $link,
                        'page' => $page->url_path,
                        'type' => 'external_'.$result['status'],
                        'status' => $result['status'],
                    ];
                } elseif ($result['status'] >= 300 && $result['status'] < 400) {
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

    private function checkExternalLink(string $url): array
    {
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
}
