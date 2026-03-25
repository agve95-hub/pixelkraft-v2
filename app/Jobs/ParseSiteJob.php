<?php

namespace App\Jobs;

use App\Models\Site;
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

    public function handle(): void
    {
        Log::info("ParseSiteJob started for [{$this->site->slug}] — full implementation in Phase 2");

        // Phase 2 will implement:
        // 1. Detect project type → select parser strategy
        // 2. Discover all pages (HTML files, built output, or components)
        // 3. For each page: extract title, meta tags, OG tags, content hash
        // 4. Run RegionDetector to find editable vs static regions
        // 5. Take screenshots via Browsershot
        // 6. Store everything in pages + editable_regions tables

        // For now, just do basic HTML file discovery
        $this->discoverHtmlPages();
    }

    private function discoverHtmlPages(): void
    {
        $repoPath = $this->site->repo_path;
        $outputDir = $this->site->build_output_dir;

        // Determine which directory to scan
        $scanPath = $outputDir
            ? "{$repoPath}/{$outputDir}"
            : $repoPath;

        if (! is_dir($scanPath)) {
            $scanPath = $repoPath;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($scanPath, \FilesystemIterator::SKIP_DOTS),
        );

        $pageCount = 0;

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'html') {
                continue;
            }

            $relativePath = str_replace($repoPath . '/', '', $file->getPathname());

            // Skip node_modules, vendor, hidden dirs
            if (preg_match('#(^|\/)(\.|node_modules|vendor)#', $relativePath)) {
                continue;
            }

            // Extract basic info from HTML
            $html = file_get_contents($file->getPathname());
            $title = $this->extractTitle($html);
            $urlPath = $this->filePathToUrlPath($relativePath, $outputDir);

            $this->site->pages()->updateOrCreate(
                ['file_path' => $relativePath],
                [
                    'url_path'     => $urlPath,
                    'title'        => $title,
                    'content_hash' => md5($html),
                    'is_published' => true,
                ]
            );

            $pageCount++;
        }

        Log::info("ParseSiteJob discovered {$pageCount} HTML pages for [{$this->site->slug}]");
    }

    private function extractTitle(string $html): ?string
    {
        if (preg_match('/<title[^>]*>([^<]+)<\/title>/i', $html, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    private function filePathToUrlPath(string $filePath, ?string $outputDir): string
    {
        $path = $filePath;

        // Strip output directory prefix
        if ($outputDir) {
            $path = preg_replace('#^' . preg_quote($outputDir, '#') . '/?#', '', $path);
        }

        // Convert index.html to /
        $path = preg_replace('#/?index\.html$#', '', $path);

        // Ensure leading slash
        $path = '/' . ltrim($path, '/');

        // Remove .html extension
        $path = preg_replace('#\.html$#', '', $path);

        return $path ?: '/';
    }

    public function tags(): array
    {
        return ['parse', "site:{$this->site->id}"];
    }
}
