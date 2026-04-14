<?php

namespace App\Services;

use App\Models\Page;
use App\Models\Site;
use App\Services\Parsers\ParsedPage;
use App\Services\Parsers\ParserInterface;
use App\Services\Parsers\RenderedPhpParser;
use App\Services\Parsers\SpaComponentParser;
use App\Services\Parsers\SsgOutputParser;
use App\Services\Parsers\StaticHtmlParser;
use Illuminate\Support\Facades\Log;

class ParserService
{
    public function __construct(
        private StaticHtmlParser $staticParser,
        private SsgOutputParser $ssgParser,
        private RenderedPhpParser $phpParser,
        private SpaComponentParser $spaParser,
    ) {}

    /**
     * Parse an entire site: discover pages, extract metadata, detect regions.
     */
    public function parseSite(Site $site): int
    {
        $parser = $this->resolveParser($site);
        $repoPath = $site->repo_path;

        Log::info("Parsing site [{$site->slug}] with strategy [{$parser->name()}]");

        $discoveredFiles = $parser->discoverPages($repoPath, $site);
        $pageCount = 0;

        foreach ($discoveredFiles as $filePath) {
            try {
                $parsed = $parser->parsePage($repoPath, $filePath, $site);

                if (! $parsed) {
                    continue;
                }

                $this->storeParsedPage($site, $parsed);
                $pageCount++;

            } catch (\Throwable $e) {
                Log::warning("Failed to parse [{$filePath}] for site [{$site->slug}]", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info("Parsed {$pageCount} pages for site [{$site->slug}]");

        // Remove pages that no longer exist in the repo
        $this->pruneDeletedPages($site, $discoveredFiles);

        return $pageCount;
    }

    /**
     * Parse a single page (for re-parsing after edits).
     */
    public function parseSinglePage(Site $site, string $filePath): ?Page
    {
        $parser = $this->resolveParser($site);
        $parsed = $parser->parsePage($site->repo_path, $filePath, $site);

        if (! $parsed) {
            return null;
        }

        return $this->storeParsedPage($site, $parsed);
    }

    /**
     * Resolve the correct parser for a site's project type.
     */
    private function resolveParser(Site $site): ParserInterface
    {
        return match ($site->project_type) {
            'static_html' => $this->staticParser,
            'php_site' => $this->phpParser,
            'hugo', 'eleventy' => $this->ssgParser,
            'astro' => $this->shouldUseSsgParser($site) ? $this->ssgParser : $this->spaParser,
            'react', 'vue',
            'svelte', 'nextjs',
            'nuxt' => $this->spaParser,
            default => $this->staticParser, // fallback
        };
    }

    /**
     * Astro can be parsed as SSG if it has a build output, otherwise SPA.
     */
    private function shouldUseSsgParser(Site $site): bool
    {
        $outputDir = $site->build_output_dir ?? 'dist';

        return is_dir("{$site->repo_path}/{$outputDir}");
    }

    /**
     * Store or update a parsed page in the database.
     */
    private function storeParsedPage(Site $site, ParsedPage $parsed): Page
    {
        $page = $site->pages()->updateOrCreate(
            ['file_path' => $parsed->filePath],
            [
                'url_path' => $parsed->urlPath,
                'title' => $parsed->title,
                'meta_description' => $parsed->metaDescription,
                'meta_keywords' => $parsed->metaKeywords,
                'og_title' => $parsed->ogTitle,
                'og_description' => $parsed->ogDescription,
                'og_image' => $parsed->ogImage,
                'canonical_url' => $parsed->canonicalUrl,
                'schema_json' => $parsed->schemaJson,
                'content_hash' => $parsed->contentHash,
                'is_published' => true,
            ]
        );

        $this->syncEditableRegions($page, $parsed->regions);

        return $page;
    }

    private function syncEditableRegions(Page $page, array $regions): void
    {
        $existingRegions = $page->editableRegions()->get();
        $existingBySelector = $existingRegions->keyBy('selector');
        $existingByMarker = $existingRegions
            ->filter(fn ($region) => ! empty($region->marker_id))
            ->keyBy('marker_id');

        $currentSelectors = [];
        $currentMarkers = [];

        foreach ($regions as $region) {
            $selector = $region['selector'] ?? null;
            if (! $selector) {
                continue;
            }

            $currentSelectors[] = $selector;
            $markerId = $region['marker_id'] ?? null;
            if ($markerId) {
                $currentMarkers[] = $markerId;
            }

            $existing = $markerId
                ? ($existingByMarker->get($markerId) ?? $existingBySelector->get($selector))
                : $existingBySelector->get($selector);

            $attributes = [
                'selector' => $selector,
                'render_selector' => $region['render_selector'] ?? $selector,
                'region_type' => $region['type'] ?? ($existing?->region_type ?? 'text'),
                'current_content' => $region['content'] ?? null,
                'source_location' => $region['source_location'] ?? null,
                'dom_fingerprint' => $region['dom_fingerprint'] ?? null,
                'source_anchor' => $region['source_anchor'] ?? null,
                'last_verified_at' => now(),
            ];

            if (! $existing || $existing->detection_method === 'auto') {
                $attributes['is_static'] = $region['is_static'] ?? false;
                $attributes['detection_method'] = 'auto';
                $attributes['confidence_score'] = $region['confidence'] ?? 0.5;
                $attributes['marker_id'] = $region['marker_id'] ?? null;
            } else {
                $attributes['is_static'] = $existing->is_static;
                $attributes['detection_method'] = $existing->detection_method;
                $attributes['confidence_score'] = $existing->confidence_score;
                $attributes['marker_id'] = $existing->marker_id;
            }

            if ($existing) {
                $existing->update($attributes);
            } else {
                $page->editableRegions()->create($attributes);
            }
        }

        $selectorsToDelete = $existingBySelector
            ->filter(function ($region, string $selector) use ($currentSelectors, $currentMarkers) {
                if ($region->detection_method !== 'auto') {
                    return false;
                }

                if ($region->marker_id && in_array($region->marker_id, $currentMarkers, true)) {
                    return false;
                }

                return ! in_array($selector, $currentSelectors, true);
            })
            ->pluck('id');

        if ($selectorsToDelete->isNotEmpty()) {
            $page->editableRegions()
                ->whereIn('id', $selectorsToDelete)
                ->delete();
        }
    }

    /**
     * Remove pages from DB that no longer exist in the repo.
     */
    private function pruneDeletedPages(Site $site, array $currentFiles): void
    {
        $existingPaths = $site->pages()->pluck('file_path')->toArray();
        $removed = array_diff($existingPaths, $currentFiles);

        if (! empty($removed)) {
            $site->pages()->whereIn('file_path', $removed)->delete();

            Log::info('Pruned '.count($removed)." deleted pages from [{$site->slug}]");
        }
    }
}
