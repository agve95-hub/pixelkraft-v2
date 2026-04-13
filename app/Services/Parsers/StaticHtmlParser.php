<?php

namespace App\Services\Parsers;

use App\Models\Site;
use Illuminate\Support\Facades\File;
use Symfony\Component\DomCrawler\Crawler;

class StaticHtmlParser implements ParserInterface
{
    public function name(): string
    {
        return 'static_html';
    }

    public function discoverPages(string $repoPath, Site $site): array
    {
        $scanPath = $this->resolveScanPath($repoPath, $site);
        $pages = [];

        if (! File::isDirectory($scanPath)) {
            return $pages;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($scanPath, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'html' && $file->getExtension() !== 'htm') {
                continue;
            }

            $relativePath = str_replace($repoPath . '/', '', $file->getPathname());

            if ($this->shouldSkip($relativePath)) {
                continue;
            }

            $pages[] = $relativePath;
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

        $html = File::get($fullPath);

        return $this->parseHtmlDocument(
            html: $html,
            filePath: $filePath,
            site: $site,
        );
    }

    public function parseHtmlDocument(string $html, string $filePath, Site $site, ?string $urlPath = null): ?ParsedPage
    {
        if (empty(trim($html))) {
            return null;
        }

        $crawler = new Crawler($html);

        // Extract metadata
        $title = $this->extractTitle($crawler);
        $metaDescription = $this->extractMeta($crawler, 'description');
        $metaKeywords = $this->extractMeta($crawler, 'keywords');
        $ogTitle = $this->extractOg($crawler, 'og:title');
        $ogDescription = $this->extractOg($crawler, 'og:description');
        $ogImage = $this->extractOg($crawler, 'og:image');
        $canonicalUrl = $this->extractCanonical($crawler);
        $schemaJson = $this->extractSchemaJson($crawler);

        // Detect editable regions
        $regions = $this->detectRegions($crawler, $filePath);

        return new ParsedPage(
            filePath: $filePath,
            urlPath: $urlPath ?? $this->filePathToUrlPath($filePath, $site->build_output_dir),
            title: $title,
            metaDescription: $metaDescription,
            metaKeywords: $metaKeywords,
            ogTitle: $ogTitle,
            ogDescription: $ogDescription,
            ogImage: $ogImage,
            canonicalUrl: $canonicalUrl,
            schemaJson: $schemaJson,
            contentHash: md5($html),
            regions: $regions,
        );
    }

    // ── Metadata Extraction ─────────────────────

    private function extractTitle(Crawler $crawler): ?string
    {
        $node = $crawler->filter('title');

        return $node->count() > 0 ? trim($node->text('')) : null;
    }

    private function extractMeta(Crawler $crawler, string $name): ?string
    {
        $node = $crawler->filter("meta[name=\"{$name}\"]");

        if ($node->count() === 0) {
            return null;
        }

        return $node->attr('content');
    }

    private function extractOg(Crawler $crawler, string $property): ?string
    {
        $node = $crawler->filter("meta[property=\"{$property}\"]");

        if ($node->count() === 0) {
            // Some sites use name instead of property
            $node = $crawler->filter("meta[name=\"{$property}\"]");
        }

        return $node->count() > 0 ? $node->attr('content') : null;
    }

    private function extractCanonical(Crawler $crawler): ?string
    {
        $node = $crawler->filter('link[rel="canonical"]');

        return $node->count() > 0 ? $node->attr('href') : null;
    }

    private function extractSchemaJson(Crawler $crawler): ?array
    {
        $schemas = [];

        $crawler->filter('script[type="application/ld+json"]')->each(function (Crawler $node) use (&$schemas) {
            $json = json_decode($node->text(''), true);

            if (is_array($json)) {
                $schemas[] = $json;
            }
        });

        return ! empty($schemas) ? $schemas : null;
    }

    // ── Region Detection ────────────────────────

    private function detectRegions(Crawler $crawler, string $filePath): array
    {
        $regions = [];

        // First: check for explicit cms:editable markers
        $regions = array_merge($regions, $this->detectMarkerRegions($crawler, $filePath));

        // Then: auto-detect content regions
        $regions = array_merge($regions, $this->autoDetectRegions($crawler, $filePath));

        return $regions;
    }

    /**
     * Find regions marked with <!-- cms:editable --> comments.
     */
    private function detectMarkerRegions(Crawler $crawler, string $filePath): array
    {
        $regions = [];
        $html = $crawler->html();

        // Match legacy cms:editable and newer pk:editable markers.
        preg_match_all(
            '/<!--\s*(?:cms:editable\s+id="([^"]+)"(?:\s+type="([^"]+)")?|pk:editable:start:([A-Za-z0-9\-_]+)(?:\s+type="([^"]+)")?)\s*-->/',
            $html,
            $matches,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE
        );

        foreach ($matches as $match) {
            $markerId = $match[1][0] ?: $match[3][0];
            $type = ($match[2][0] ?? $match[4][0] ?? 'text') ?: 'text';
            $offset = $match[0][1];

            $closePattern = '/<!--\s*(?:\/cms:editable|pk:editable:end:' . preg_quote($markerId, '/') . ')\s*-->/';
            $remaining = substr($html, $offset + strlen($match[0][0]));

            if (preg_match($closePattern, $remaining, $closeMatch, PREG_OFFSET_CAPTURE)) {
                $content = substr($remaining, 0, $closeMatch[0][1]);
                $content = trim($content);

                $regions[] = [
                    'selector'        => "[data-cms-id=\"{$markerId}\"]",
                    'render_selector' => "[data-cms-id=\"{$markerId}\"]",
                    'type'            => $type,
                    'is_static'       => false,
                    'confidence'      => 1.0,
                    'content'         => $content,
                    'marker_id'       => $markerId,
                    'dom_fingerprint' => $this->buildContentFingerprint($type, $content, []),
                    'source_location' => [
                        'file'   => $filePath,
                        'marker' => $markerId,
                    ],
                    'source_anchor' => [
                        'file' => $filePath,
                        'marker_id' => $markerId,
                        'context_hash' => sha1($content),
                    ],
                ];
            }
        }

        return $regions;
    }

    /**
     * Auto-detect editable regions using heuristics.
     */
    private function autoDetectRegions(Crawler $crawler, string $filePath): array
    {
        $regions = [];
        $index = 0;

        // Scan semantic content elements
        $contentSelectors = [
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
            'p',
            'article', 'section > .content', 'main',
            'figcaption',
            'img[src][alt]',
            'a[href]',
            'button',
            'span',
            'em',
            'strong',
            'blockquote',
            'ul > li', 'ol > li',
        ];

        foreach ($contentSelectors as $selector) {
            try {
                $crawler->filter($selector)->each(function (Crawler $node) use (&$regions, &$index, $filePath) {
                    $score = $this->scoreElement($node);

                    // Skip very low confidence elements
                    if ($score < 0.2) {
                        return;
                    }

                    $tagName = $node->nodeName();
                    $text = trim($node->text(''));
                    $isImage = ($tagName === 'img');
                    $isInlineTextElement = in_array($tagName, ['span', 'em', 'strong'], true);

                    if ($isInlineTextElement && $this->hasElementChildren($node)) {
                        return;
                    }

                    // Skip empty non-image elements
                    if (! $isImage && empty($text)) {
                        return;
                    }

                    // Skip very short text that's likely navigation
                    if (! $isImage && mb_strlen($text) < 5 && ! in_array($tagName, ['h1', 'h2', 'h3', 'span', 'em', 'strong'], true)) {
                        return;
                    }

                    // Build a unique selector
                    $cssSelector = $this->buildSelector($node, $index);

                    $type = match (true) {
                        $isImage                        => 'image',
                        $tagName === 'a'                => 'link',
                        in_array($tagName, ['ul', 'ol']) => 'list',
                        in_array($tagName, ['section', 'article', 'main']) => 'section',
                        default                         => 'text',
                    };

                    $regions[] = [
                        'selector'        => $cssSelector,
                        'render_selector' => $cssSelector,
                        'type'            => $type,
                        'is_static'       => $score < 0.5,
                        'confidence'      => round($score, 2),
                        'content'         => $isImage ? $node->attr('src') : mb_substr($text, 0, 500),
                        'source_location' => [
                            'file'     => $filePath,
                            'selector' => $cssSelector,
                        ],
                        'dom_fingerprint' => $this->buildFingerprint($node, $cssSelector, $type, $isImage ? ($node->attr('src') ?? '') : $text),
                        'source_anchor' => [
                            'file' => $filePath,
                            'selector' => $cssSelector,
                            'context_hash' => sha1($isImage ? ($node->attr('src') ?? '') : $text),
                            'tag' => $tagName,
                            'type' => $type,
                        ],
                    ];

                    $index++;
                });
            } catch (\Throwable $e) {
                // Skip selectors that fail on malformed HTML
                continue;
            }
        }

        return $regions;
    }

    /**
     * Score an element's likelihood of being editable content (0-1).
     */
    private function scoreElement(Crawler $node): float
    {
        $score = 0.0;
        $tagName = $node->nodeName();
        $text = trim($node->text(''));

        // Semantic content tags get a boost
        $contentTags = ['h1' => 0.35, 'h2' => 0.3, 'h3' => 0.28, 'h4' => 0.25, 'h5' => 0.22, 'h6' => 0.2, 'p' => 0.25, 'article' => 0.2, 'figcaption' => 0.25, 'blockquote' => 0.25, 'li' => 0.15, 'button' => 0.18, 'span' => 0.16, 'em' => 0.2, 'strong' => 0.2];
        $score += $contentTags[$tagName] ?? 0;

        // Meaningful text length
        if (mb_strlen($text) > 20) {
            $score += 0.2;
        } elseif (mb_strlen($text) > 5) {
            $score += 0.1;
        }

        // Images with alt text
        if ($tagName === 'img' && $node->attr('alt')) {
            $score += 0.3;
        }

        // Inside <main> or [role="main"]
        try {
            $parent = $node->ancestors();
            $parentHtml = $parent->count() > 0 ? $parent->first()->nodeName() : '';

            // Walk up to check if inside main
            $current = $node;
            for ($i = 0; $i < 10; $i++) {
                $ancestors = $current->ancestors();
                if ($ancestors->count() === 0) {
                    break;
                }

                $ancestorNode = $ancestors->first();
                $ancestorTag = $ancestorNode->nodeName();
                $ancestorRole = '';

                try {
                    $ancestorRole = $ancestorNode->attr('role') ?? '';
                } catch (\Throwable $e) {
                    // ignore
                }

                if ($ancestorTag === 'main' || $ancestorRole === 'main') {
                    $score += 0.15;
                    break;
                }

                // Penalty for nav/header/footer ancestors
                if (in_array($ancestorTag, ['nav', 'header', 'footer'])) {
                    $score -= 0.3;
                    break;
                }

                $current = $ancestors;
            }
        } catch (\Throwable $e) {
            // ignore ancestor traversal errors
        }

        // Check for unique class/id
        try {
            $id = $node->attr('id');
            $class = $node->attr('class');

            if ($id && ! preg_match('/^(container|wrapper|main|app)$/i', $id)) {
                $score += 0.1;
            }

            if ($class && preg_match('/(content|text|title|hero|heading|desc|body|intro|summary)/i', $class)) {
                $score += 0.1;
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return max(0, min(1, $score));
    }

    private function hasElementChildren(Crawler $node): bool
    {
        $domNode = $node->getNode(0);

        if (! $domNode instanceof \DOMElement) {
            return false;
        }

        foreach ($domNode->childNodes as $childNode) {
            if ($childNode instanceof \DOMElement) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build a CSS selector for an element.
     */
    private function buildSelector(Crawler $node, int $index): string
    {
        $domNode = $node->getNode(0);

        if (! $domNode instanceof \DOMElement) {
            return $node->nodeName();
        }

        if ($domNode->hasAttribute('id')) {
            return '#' . $domNode->getAttribute('id');
        }

        $segments = [];
        $current = $domNode;

        while ($current instanceof \DOMElement) {
            $tagName = strtolower($current->tagName);

            if ($current->hasAttribute('id')) {
                array_unshift($segments, '#' . $current->getAttribute('id'));
                break;
            }

            array_unshift($segments, $tagName . ':nth-of-type(' . $this->nthOfType($current) . ')');

            $parent = $current->parentNode;
            if (! $parent instanceof \DOMElement || in_array(strtolower($parent->tagName), ['html'], true)) {
                break;
            }

            $current = $parent;
        }

        return implode(' > ', $segments) ?: strtolower($domNode->tagName) . ':nth-of-type(' . max(1, $index + 1) . ')';
    }

    private function nthOfType(\DOMElement $element): int
    {
        $position = 1;
        $tagName = $element->tagName;
        $sibling = $element->previousSibling;

        while ($sibling) {
            if ($sibling instanceof \DOMElement && $sibling->tagName === $tagName) {
                $position++;
            }

            $sibling = $sibling->previousSibling;
        }

        return $position;
    }

    private function buildFingerprint(Crawler $node, string $selector, string $type, string $content): array
    {
        $domNode = $node->getNode(0);
        $attributes = [];

        if ($domNode instanceof \DOMElement) {
            foreach ($domNode->attributes as $attribute) {
                if (str_starts_with($attribute->name, 'data-pk-')) {
                    continue;
                }

                $attributes[$attribute->name] = $attribute->value;
            }
        }

        return $this->buildContentFingerprint($type, $content, [
            'selector' => $selector,
            'tag' => $domNode instanceof \DOMElement ? strtolower($domNode->tagName) : null,
            'attributes_hash' => sha1(json_encode($attributes, JSON_UNESCAPED_SLASHES)),
        ]);
    }

    private function buildContentFingerprint(string $type, string $content, array $extra): array
    {
        return array_merge($extra, [
            'type' => $type,
            'content_hash' => sha1(trim($content)),
            'text_length' => mb_strlen(trim(strip_tags($content))),
        ]);
    }

    // ── Helpers ──────────────────────────────────

    private function resolveScanPath(string $repoPath, Site $site): string
    {
        if ($site->build_output_dir && File::isDirectory("{$repoPath}/{$site->build_output_dir}")) {
            return "{$repoPath}/{$site->build_output_dir}";
        }

        // Check common static site directories
        $candidates = ['public', 'src', 'dist', '.'];

        foreach ($candidates as $dir) {
            $path = $dir === '.' ? $repoPath : "{$repoPath}/{$dir}";

            if (File::isDirectory($path) && ! empty(File::glob("{$path}/*.html"))) {
                return $path;
            }
        }

        return $repoPath;
    }

    private function filePathToUrlPath(string $filePath, ?string $outputDir): string
    {
        $path = $filePath;

        if ($outputDir) {
            $path = preg_replace('#^' . preg_quote($outputDir, '#') . '/?#', '', $path);
        }

        // Also strip common source dirs
        $path = preg_replace('#^(public|src|dist)/?#', '', $path);

        $path = preg_replace('#/?index\.html?$#', '', $path);
        $path = preg_replace('#\.html?$#', '', $path);
        $path = '/' . ltrim($path, '/');

        return $path ?: '/';
    }

    private function shouldSkip(string $path): bool
    {
        $skipPatterns = [
            '#(^|/)\.#',
            '#(^|/)node_modules/#',
            '#(^|/)vendor/#',
            '#(^|/)__pycache__/#',
            '#(^|/)(\.next|\.nuxt|\.svelte-kit)/#',
        ];

        foreach ($skipPatterns as $pattern) {
            if (preg_match($pattern, $path)) {
                return true;
            }
        }

        return false;
    }
}
