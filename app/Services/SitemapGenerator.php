<?php

namespace App\Services;

use App\Models\Site;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class SitemapGenerator
{
    /**
     * Generate an XML sitemap for a site and write it to the deploy directory.
     */
    public function generate(Site $site): ?string
    {
        $pages = $site->pages()
            ->where('is_published', true)
            ->orderBy('url_path')
            ->get();

        if ($pages->isEmpty()) {
            return null;
        }

        $baseUrl = $site->domain ? "https://{$site->domain}" : '';

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($pages as $page) {
            $url = $baseUrl . ($page->url_path ?? '/');
            $lastmod = $page->updated_at?->format('Y-m-d') ?? now()->format('Y-m-d');

            $priority = $this->calculatePriority($page);
            $changefreq = $this->calculateChangeFreq($page);

            $xml .= "  <url>\n";
            $xml .= "    <loc>{$url}</loc>\n";
            $xml .= "    <lastmod>{$lastmod}</lastmod>\n";
            $xml .= "    <changefreq>{$changefreq}</changefreq>\n";
            $xml .= "    <priority>{$priority}</priority>\n";
            $xml .= "  </url>\n";
        }

        // Add blog posts
        foreach ($site->blogPosts()->where('status', 'published')->get() as $post) {
            $url = $baseUrl . '/blog/' . $post->slug;
            $lastmod = $post->published_at?->format('Y-m-d') ?? $post->updated_at->format('Y-m-d');

            $xml .= "  <url>\n";
            $xml .= "    <loc>{$url}</loc>\n";
            $xml .= "    <lastmod>{$lastmod}</lastmod>\n";
            $xml .= "    <changefreq>monthly</changefreq>\n";
            $xml .= "    <priority>0.6</priority>\n";
            $xml .= "  </url>\n";
        }

        $xml .= "</urlset>\n";

        // Write to deploy path
        $outputPath = $this->writeSitemap($site, $xml);

        // Also write to repo for git tracking
        $this->writeSitemapToRepo($site, $xml);

        Log::info("Generated sitemap for [{$site->slug}] with " . $pages->count() . " pages");

        return $outputPath;
    }

    private function writeSitemap(Site $site, string $xml): ?string
    {
        if (! $site->deploy_path) {
            return null;
        }

        $path = "{$site->deploy_path}/sitemap.xml";
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $xml);

        return $path;
    }

    private function writeSitemapToRepo(Site $site, string $xml): void
    {
        if (! $site->repo_path || ! File::isDirectory($site->repo_path)) {
            return;
        }

        $outputDir = $site->build_output_dir;
        $targetDir = $outputDir ? "{$site->repo_path}/{$outputDir}" : $site->repo_path;

        if (File::isDirectory($targetDir)) {
            File::put("{$targetDir}/sitemap.xml", $xml);
        }
    }

    private function calculatePriority($page): string
    {
        $path = $page->url_path ?? '/';

        if ($path === '/') {
            return '1.0';
        }

        $depth = substr_count(trim($path, '/'), '/');

        return match (true) {
            $depth === 0 => '0.8',
            $depth === 1 => '0.6',
            default      => '0.4',
        };
    }

    private function calculateChangeFreq($page): string
    {
        $path = $page->url_path ?? '/';

        if ($path === '/') {
            return 'weekly';
        }

        if (str_contains($path, 'blog') || str_contains($path, 'news')) {
            return 'weekly';
        }

        return 'monthly';
    }
}
