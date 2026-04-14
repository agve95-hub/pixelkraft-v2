<?php

namespace App\Services\Parsers;

use App\Models\Site;
use App\Services\PagePreviewService;
use App\Services\SiteRuntimeService;
use Illuminate\Support\Facades\File;

class SpaComponentParser implements ParserInterface
{
    public function __construct(
        private StaticHtmlParser $htmlParser,
        private PagePreviewService $previews,
        private SiteRuntimeService $runtime,
    ) {}

    public function name(): string
    {
        return 'spa_component';
    }

    public function discoverPages(string $repoPath, Site $site): array
    {
        $extensions = $this->getExtensions($site->project_type);
        $searchDirs = $this->getSearchDirs($repoPath, $site->project_type);

        $pages = [];

        foreach ($searchDirs as $dir) {
            if (! File::isDirectory($dir)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            );

            foreach ($iterator as $file) {
                if (! in_array($file->getExtension(), $extensions, true)) {
                    continue;
                }

                $relativePath = str_replace($repoPath.'/', '', $file->getPathname());

                if ($this->shouldSkip($relativePath)) {
                    continue;
                }

                // Filter to page-like components (not utility/layout components)
                if ($this->isPageComponent($relativePath, $site->project_type)) {
                    $pages[] = $relativePath;
                }
            }
        }

        sort($pages);

        return $pages;
    }

    public function parsePage(string $repoPath, string $filePath, Site $site): ?ParsedPage
    {
        $fullPath = "{$repoPath}/{$filePath}";

        if (! File::exists($fullPath)) {
            return null;
        }

        $content = File::get($fullPath);
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        $urlPath = $this->componentPathToUrlPath($filePath, $site->project_type);

        // Extract content based on file type
        $extractedContent = match ($extension) {
            'jsx', 'tsx' => $this->parseJsx($content),
            'vue' => $this->parseVueSfc($content),
            'svelte' => $this->parseSvelte($content),
            'astro' => $this->parseAstro($content),
            default => $this->parseGeneric($content),
        };

        $renderedPreview = $this->parseRenderedPreview($repoPath, $filePath, $urlPath, $content, $site);
        if ($renderedPreview) {
            return $renderedPreview;
        }

        if (! $extractedContent) {
            return new ParsedPage(
                filePath: $filePath,
                urlPath: $urlPath,
                contentHash: md5($content),
                regions: [],
            );
        }

        $regions = $this->buildRegions($extractedContent, $filePath);

        return new ParsedPage(
            filePath: $filePath,
            urlPath: $urlPath,
            title: $extractedContent['title'] ?? null,
            metaDescription: $extractedContent['meta_description'] ?? null,
            contentHash: md5($content),
            regions: $regions,
        );
    }

    // ── JSX/TSX Parsing ─────────────────────────

    private function parseJsx(string $content): ?array
    {
        $result = [
            'title' => null,
            'meta_description' => null,
            'text_nodes' => [],
            'images' => [],
        ];

        // Extract text content from JSX elements
        // Matches content between > and < that isn't whitespace-only
        preg_match_all('/>\s*([^<>{}\n]+?)\s*</', $content, $textMatches);

        foreach ($textMatches[1] as $text) {
            $text = trim($text);

            if (empty($text) || mb_strlen($text) < 3) {
                continue;
            }

            // Skip JS expressions, imports, etc.
            if (preg_match('/^(import|export|const|let|var|function|return|if|else)/', $text)) {
                continue;
            }

            $result['text_nodes'][] = $text;
        }

        // Extract string props that contain meaningful text
        preg_match_all('/(?:title|heading|label|text|description|alt|placeholder)=\{?"([^"]+)"\}?/', $content, $propMatches);

        foreach ($propMatches[1] as $text) {
            if (mb_strlen($text) > 3) {
                $result['text_nodes'][] = $text;
            }
        }

        // Extract img src
        preg_match_all('/(?:<img|<Image)[^>]*src=\{?"([^"{}]+)"\}?/', $content, $imgMatches);
        $result['images'] = $imgMatches[1] ?? [];

        // Try to find page title
        if (preg_match('/<title[^>]*>([^<]+)<\/title>/i', $content, $titleMatch)) {
            $result['title'] = trim($titleMatch[1]);
        } elseif (preg_match('/<h1[^>]*>([^<{]+)</', $content, $h1Match)) {
            $result['title'] = trim($h1Match[1]);
        }

        // Meta description from Head/Helmet
        if (preg_match('/content=\{?"([^"]+)"\}?.*name=\{?"description"\}?/s', $content, $metaMatch)) {
            $result['meta_description'] = trim($metaMatch[1]);
        } elseif (preg_match('/name=\{?"description"\}?.*content=\{?"([^"]+)"\}?/s', $content, $metaMatch2)) {
            $result['meta_description'] = trim($metaMatch2[1]);
        }

        return empty($result['text_nodes']) && empty($result['images']) ? null : $result;
    }

    // ── Vue SFC Parsing ─────────────────────────

    private function parseVueSfc(string $content): ?array
    {
        $result = [
            'title' => null,
            'meta_description' => null,
            'text_nodes' => [],
            'images' => [],
        ];

        // Extract <template> block
        if (preg_match('/<template>(.*?)<\/template>/s', $content, $templateMatch)) {
            $template = $templateMatch[1];

            // Extract text between tags (excluding Vue directives)
            preg_match_all('/>\s*([^<>{}\n]+?)\s*</', $template, $textMatches);

            foreach ($textMatches[1] as $text) {
                $text = trim($text);

                if (empty($text) || mb_strlen($text) < 3) {
                    continue;
                }

                // Skip Vue interpolation-only content like {{ variable }}
                if (preg_match('/^\{\{.*\}\}$/', $text)) {
                    continue;
                }

                $result['text_nodes'][] = $text;
            }

            // Extract images
            preg_match_all('/<img[^>]*(?::src|src)=\{?"([^"{}]+)"\}?/', $template, $imgMatches);
            $result['images'] = $imgMatches[1] ?? [];

            // Extract h1 for title
            if (preg_match('/<h1[^>]*>([^<{]+)</', $template, $h1Match)) {
                $result['title'] = trim($h1Match[1]);
            }
        }

        return empty($result['text_nodes']) && empty($result['images']) ? null : $result;
    }

    // ── Svelte Parsing ──────────────────────────

    private function parseSvelte(string $content): ?array
    {
        $result = [
            'title' => null,
            'meta_description' => null,
            'text_nodes' => [],
            'images' => [],
        ];

        // Remove <script> and <style> blocks
        $html = preg_replace('/<script[^>]*>.*?<\/script>/s', '', $content);
        $html = preg_replace('/<style[^>]*>.*?<\/style>/s', '', $html);

        // Extract text
        preg_match_all('/>\s*([^<>{}\n]+?)\s*</', $html, $textMatches);

        foreach ($textMatches[1] as $text) {
            $text = trim($text);

            if (empty($text) || mb_strlen($text) < 3) {
                continue;
            }

            if (preg_match('/^\{.*\}$/', $text)) {
                continue;
            }

            $result['text_nodes'][] = $text;
        }

        // Extract images
        preg_match_all('/<img[^>]*src="([^"]+)"/', $html, $imgMatches);
        $result['images'] = $imgMatches[1] ?? [];

        // Title from svelte:head or h1
        if (preg_match('/<svelte:head>.*?<title>([^<]+)<\/title>.*?<\/svelte:head>/s', $content, $titleMatch)) {
            $result['title'] = trim($titleMatch[1]);
        } elseif (preg_match('/<h1[^>]*>([^<{]+)</', $html, $h1Match)) {
            $result['title'] = trim($h1Match[1]);
        }

        return empty($result['text_nodes']) && empty($result['images']) ? null : $result;
    }

    // ── Astro Parsing ───────────────────────────

    private function parseAstro(string $content): ?array
    {
        // Astro files have frontmatter (---) + template HTML
        $parts = preg_split('/^---$/m', $content, 3);

        $frontmatter = count($parts) >= 3 ? $parts[1] : '';
        $template = count($parts) >= 3 ? $parts[2] : ($parts[1] ?? $content);

        $result = [
            'title' => null,
            'meta_description' => null,
            'text_nodes' => [],
            'images' => [],
        ];

        // Extract frontmatter values
        if (preg_match('/title\s*[:=]\s*["\']([^"\']+)["\']/', $frontmatter, $titleMatch)) {
            $result['title'] = trim($titleMatch[1]);
        }

        if (preg_match('/description\s*[:=]\s*["\']([^"\']+)["\']/', $frontmatter, $descMatch)) {
            $result['meta_description'] = trim($descMatch[1]);
        }

        // Parse template HTML (similar to JSX)
        preg_match_all('/>\s*([^<>{}\n]+?)\s*</', $template, $textMatches);

        foreach ($textMatches[1] as $text) {
            $text = trim($text);

            if (! empty($text) && mb_strlen($text) >= 3) {
                $result['text_nodes'][] = $text;
            }
        }

        preg_match_all('/<img[^>]*src="([^"]+)"/', $template, $imgMatches);
        $result['images'] = $imgMatches[1] ?? [];

        return empty($result['text_nodes']) && empty($result['images']) ? null : $result;
    }

    // ── Generic Fallback ────────────────────────

    private function parseGeneric(string $content): ?array
    {
        $result = ['text_nodes' => [], 'images' => [], 'title' => null, 'meta_description' => null];

        preg_match_all('/>\s*([^<>{}\n]+?)\s*</', $content, $matches);

        foreach ($matches[1] as $text) {
            $text = trim($text);

            if (! empty($text) && mb_strlen($text) >= 5) {
                $result['text_nodes'][] = $text;
            }
        }

        return empty($result['text_nodes']) ? null : $result;
    }

    // ── Region Building ─────────────────────────

    private function buildRegions(array $extracted, string $filePath): array
    {
        $regions = [];
        $index = 0;

        foreach ($extracted['text_nodes'] as $text) {
            $regions[] = [
                'selector' => "[data-pk-region=\"spa-{$index}\"]",
                'type' => 'text',
                'is_static' => false,
                'confidence' => 0.6, // Lower confidence for regex-extracted content
                'content' => mb_substr($text, 0, 500),
                'source_location' => [
                    'file' => $filePath,
                    'source_type' => 'component',
                ],
            ];
            $index++;
        }

        foreach ($extracted['images'] ?? [] as $src) {
            $regions[] = [
                'selector' => "[data-pk-region=\"spa-{$index}\"]",
                'type' => 'image',
                'is_static' => false,
                'confidence' => 0.5,
                'content' => $src,
                'source_location' => [
                    'file' => $filePath,
                    'source_type' => 'component',
                ],
            ];
            $index++;
        }

        return $regions;
    }

    private function parseRenderedPreview(
        string $repoPath,
        string $sourceFilePath,
        string $urlPath,
        string $sourceContent,
        Site $site,
    ): ?ParsedPage {
        $builtPreviewPath = $this->previews->findBuiltHtmlPath($site, $urlPath);

        if (! $builtPreviewPath) {
            return $this->parseRuntimePreview($sourceFilePath, $urlPath, $sourceContent, $site);
        }

        $parsed = $this->htmlParser->parsePage($repoPath, $builtPreviewPath, $site);

        if (! $parsed) {
            return null;
        }

        return $this->mapRenderedPreviewToSource(
            parsed: $parsed,
            sourceFilePath: $sourceFilePath,
            sourceContent: $sourceContent,
            urlPath: $urlPath,
            sourceType: 'component_preview',
            previewReference: $builtPreviewPath,
        );
    }

    private function parseRuntimePreview(
        string $sourceFilePath,
        string $urlPath,
        string $sourceContent,
        Site $site,
    ): ?ParsedPage {
        if (! $this->runtime->usesRuntimeServer($site) || ! $this->runtime->isReachable($site, $urlPath)) {
            return null;
        }

        $response = $this->runtime->fetch($site, $urlPath);

        if (! $response || $response->status() >= 500) {
            return null;
        }

        $contentType = strtolower((string) $response->header('Content-Type'));
        if ($contentType !== '' && ! str_contains($contentType, 'html')) {
            return null;
        }

        $parsed = $this->htmlParser->parseHtmlDocument(
            html: $response->body(),
            filePath: $sourceFilePath,
            site: $site,
            urlPath: $urlPath,
        );

        if (! $parsed) {
            return null;
        }

        return $this->mapRenderedPreviewToSource(
            parsed: $parsed,
            sourceFilePath: $sourceFilePath,
            sourceContent: $sourceContent,
            urlPath: $urlPath,
            sourceType: 'component_runtime_preview',
            previewReference: $this->runtime->previewBaseUrl($site).$urlPath,
        );
    }

    private function mapRenderedPreviewToSource(
        ParsedPage $parsed,
        string $sourceFilePath,
        string $sourceContent,
        string $urlPath,
        string $sourceType,
        string $previewReference,
    ): ParsedPage {
        $regions = array_map(function (array $region) use ($sourceFilePath, $sourceType, $previewReference) {
            $sourceLocation = $region['source_location'] ?? [];
            $sourceLocation['file'] = $sourceFilePath;
            $sourceLocation['source_type'] = $sourceType;
            $sourceLocation['preview_file'] = $previewReference;

            $region['source_location'] = $sourceLocation;

            return $region;
        }, $parsed->regions);

        return new ParsedPage(
            filePath: $sourceFilePath,
            urlPath: $urlPath,
            title: $parsed->title,
            metaDescription: $parsed->metaDescription,
            metaKeywords: $parsed->metaKeywords,
            ogTitle: $parsed->ogTitle,
            ogDescription: $parsed->ogDescription,
            ogImage: $parsed->ogImage,
            canonicalUrl: $parsed->canonicalUrl,
            schemaJson: $parsed->schemaJson,
            contentHash: md5($sourceContent),
            regions: $regions,
        );
    }

    // ── Helpers ──────────────────────────────────

    private function getExtensions(string $projectType): array
    {
        return match ($projectType) {
            'react', 'nextjs' => ['jsx', 'tsx'],
            'vue', 'nuxt' => ['vue'],
            'svelte' => ['svelte'],
            'astro' => ['astro', 'md', 'mdx'],
            default => ['jsx', 'tsx', 'vue', 'svelte', 'astro'],
        };
    }

    private function getSearchDirs(string $repoPath, string $projectType): array
    {
        $candidates = match ($projectType) {
            'nextjs' => ['app', 'pages', 'src/app', 'src/pages'],
            'nuxt' => ['pages', 'components'],
            'astro' => ['src/pages', 'src/content'],
            default => ['src/pages', 'src/views', 'src/routes', 'pages', 'src/components', 'src'],
        };

        return array_map(fn ($dir) => "{$repoPath}/{$dir}", $candidates);
    }

    private function isPageComponent(string $relativePath, string $projectType): bool
    {
        $normalizedPath = trim(str_replace('\\', '/', $relativePath), '/');

        if ($projectType === 'nextjs') {
            return $this->isNextPageComponent($normalizedPath);
        }

        // Page-like patterns by framework
        $pagePatterns = match ($projectType) {
            'nuxt' => ['#^pages/#'],
            'astro' => ['#^src/pages/#', '#^src/content/#'],
            'svelte' => ['#^src/routes/#'],
            default => ['#(page|view|screen|route)#i', '#^src/(pages|views|routes)/#'],
        };

        foreach ($pagePatterns as $pattern) {
            if (preg_match($pattern, $normalizedPath)) {
                return true;
            }
        }

        // Also include any component file in a pages/views directory
        return (bool) preg_match('#/(pages|views|routes)/#', $normalizedPath);
    }

    private function componentPathToUrlPath(string $filePath, string $projectType): string
    {
        $path = $filePath;

        // Strip common prefixes
        $path = preg_replace('#^src/(pages|views|routes|app)/?#', '', $path);
        $path = preg_replace('#^(pages|app)/?#', '', $path);

        // Strip extension
        $path = preg_replace('#\.(jsx|tsx|vue|svelte|astro|md|mdx)$#', '', $path);

        $segments = array_values(array_filter(
            explode('/', trim((string) $path, '/')),
            fn (string $segment) => $segment !== '' && ! preg_match('/^\(.*\)$/', $segment) && ! str_starts_with($segment, '@')
        ));

        $path = implode('/', $segments);

        // Strip index
        $path = preg_replace('#/?index$#', '', $path);

        // Handle Next.js page.tsx convention
        $path = preg_replace('#/?page$#', '', $path);

        // Handle Next.js catch-all routes before simple dynamic segments.
        $path = preg_replace('/\[\[\.\.\.([^\]]+)\]\]/', ':$1*', $path);
        $path = preg_replace('/\[\.\.\.([^\]]+)\]/', ':$1*', $path);
        $path = preg_replace('/\[([^\]]+)\]/', ':$1', $path);

        $path = '/'.ltrim($path, '/');

        return $path ?: '/';
    }

    private function isNextPageComponent(string $relativePath): bool
    {
        if (preg_match('#^(src/)?app/#', $relativePath)) {
            return (bool) preg_match('#(^|/)page\.(jsx|tsx)$#', $relativePath);
        }

        if (! preg_match('#^(src/)?pages/#', $relativePath)) {
            return false;
        }

        if (preg_match('#^(src/)?pages/api/#', $relativePath)) {
            return false;
        }

        $basename = pathinfo($relativePath, PATHINFO_FILENAME);

        if (in_array($basename, ['_app', '_document', '_error', '404', '500'], true)) {
            return false;
        }

        return (bool) preg_match('#\.(jsx|tsx)$#', $relativePath);
    }

    private function shouldSkip(string $path): bool
    {
        return (bool) preg_match('#(node_modules|\.next|\.nuxt|\.svelte-kit|__tests__|__mocks__|\.test\.|\.spec\.)#', $path);
    }
}
