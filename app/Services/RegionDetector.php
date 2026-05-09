<?php

namespace App\Services;

use App\Models\EditableRegion;
use App\Models\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class RegionDetector
{
    /**
     * Reclassify all auto-detected regions for a page based on cross-page analysis.
     *
     * This runs after initial parsing to refine classifications by comparing
     * regions across all pages of a site (e.g., nav items that repeat everywhere
     * are likely static theme elements).
     */
    public function refineClassifications(Page $page): int
    {
        $site = $page->site;
        $regions = $page->editableRegions()->where('detection_method', 'auto')->get();
        $refined = 0;

        // Get content from other pages for cross-page comparison
        $otherPages = $site->pages()
            ->where('id', '!=', $page->id)
            ->with('editableRegions')
            ->limit(10)
            ->get();

        $repeatCounts = $this->buildRepeatMap($otherPages);

        foreach ($regions as $region) {
            $newScore = $this->refineScore($region, $repeatCounts);

            if (abs($newScore - $region->confidence_score) > 0.05) {
                $region->update([
                    'confidence_score' => round($newScore, 2),
                    'is_static' => $newScore < 0.5,
                ]);
                $refined++;
            }
        }

        if ($refined > 0) {
            Log::info("Refined {$refined} region classifications for page [{$page->url_path}]");
        }

        return $refined;
    }

    /**
     * Confirm a region as editable (user action from dashboard).
     */
    public function confirmAsEditable(EditableRegion $region, ?string $markerId = null): void
    {
        $sourceAnchor = $region->source_anchor ?? [];

        if ($markerId) {
            $sourceAnchor['marker_id'] = $markerId;
            $sourceAnchor['verified_via'] = 'marker';
        } else {
            $sourceAnchor['verified_via'] = 'manual';
        }

        $region->update([
            'is_static' => false,
            'detection_method' => $markerId ? 'marker' : 'manual',
            'marker_id' => $markerId,
            'confidence_score' => 1.0,
            'source_anchor' => $sourceAnchor,
            'last_verified_at' => now(),
        ]);
    }

    /**
     * Confirm a region as static/locked (user action from dashboard).
     */
    public function confirmAsStatic(EditableRegion $region): void
    {
        $sourceAnchor = $region->source_anchor ?? [];
        $sourceAnchor['verified_via'] = 'manual';
        $sourceAnchor['locked'] = true;

        $region->update([
            'is_static' => true,
            'detection_method' => 'manual',
            'confidence_score' => 1.0,
            'source_anchor' => $sourceAnchor,
            'last_verified_at' => now(),
        ]);
    }

    /**
     * Generate a marker ID for a confirmed region.
     */
    public function generateMarkerId(EditableRegion $region): string
    {
        $page = $region->page;
        $baseName = $region->region_type;

        // Create a readable ID like "hero-title", "about-paragraph-1"
        $content = $region->current_content ?? '';
        $slug = str()->slug(str()->limit(strip_tags($content), 30, ''));

        if (empty($slug)) {
            $slug = $region->id;
        }

        return "{$baseName}-{$slug}-".substr((string) $region->id, 0, 8);
    }

    /**
     * Inject cms:editable markers into the source HTML file.
     */
    public function injectMarkers(string $html, array $confirmedRegions): string
    {
        // Sort regions by their position in the HTML (reverse order to preserve offsets)
        // For now, we inject markers based on CSS selectors — this is a simplification
        // that works for ID and class-based selectors
        foreach ($confirmedRegions as $region) {
            if (empty($region['marker_id'])) {
                continue;
            }

            $markerId = $region['marker_id'];
            $type = $region['region_type'] ?? 'text';
            $openMarker = "<!-- ui:editable:start:{$markerId} type=\"{$type}\" -->";
            $closeMarker = "<!-- ui:editable:end:{$markerId} -->";

            // Try to find the element by its selector and wrap it
            $html = $this->wrapElementWithMarker($html, $region, $openMarker, $closeMarker);
        }

        return $html;
    }

    // ── Private Methods ─────────────────────────

    /**
     * Build a map of content that repeats across pages.
     */
    /**
     * @param  Collection<int, Page>  $otherPages
     * @return array<string, int>
     */
    private function buildRepeatMap(iterable $otherPages): array
    {
        $contentCounts = [];

        foreach ($otherPages as $otherPage) {
            foreach ($otherPage->editableRegions as $region) {
                $content = trim($region->current_content ?? '');

                if (empty($content) || mb_strlen($content) < 5) {
                    continue;
                }

                // Normalize for comparison
                $key = md5(strtolower($content));
                $contentCounts[$key] = ($contentCounts[$key] ?? 0) + 1;
            }
        }

        return $contentCounts;
    }

    /**
     * Refine a region's confidence score using cross-page data.
     */
    private function refineScore(EditableRegion $region, array $repeatCounts): float
    {
        $score = $region->confidence_score;
        $content = trim($region->current_content ?? '');

        if (empty($content)) {
            return $score;
        }

        $key = md5(strtolower($content));
        $repeatCount = $repeatCounts[$key] ?? 0;

        // Content that appears on many pages is likely static (nav, footer, etc.)
        if ($repeatCount >= 3) {
            $score -= 0.3;
        } elseif ($repeatCount >= 2) {
            $score -= 0.15;
        }

        // Content that's unique to this page is likely dynamic
        if ($repeatCount === 0) {
            $score += 0.1;
        }

        return max(0, min(1, $score));
    }

    /**
     * Wrap an HTML element with CMS markers using simple pattern matching.
     */
    private function wrapElementWithMarker(string $html, array $region, string $openMarker, string $closeMarker): string
    {
        $selector = $region['selector'] ?? '';

        // Handle #id selectors
        if (preg_match('/^#(.+)$/', $selector, $m)) {
            $id = preg_quote($m[1], '/');
            $pattern = '/(<[^>]*\bid=["\']'.$id.'["\'][^>]*>)(.*?)(<\/[^>]+>)/s';

            return preg_replace($pattern, "{$openMarker}\n$1$2$3\n{$closeMarker}", $html, 1);
        }

        // Handle tag.class selectors
        if (preg_match('/^(\w+)\.(.+)$/', $selector, $m)) {
            $tag = preg_quote($m[1], '/');
            $class = preg_quote($m[2], '/');
            $pattern = '/(<'.$tag.'[^>]*\bclass=["\'][^"\']*\b'.$class.'\b[^"\']*["\'][^>]*>)(.*?)(<\/'.$tag.'>)/s';

            return preg_replace($pattern, "{$openMarker}\n$1$2$3\n{$closeMarker}", $html, 1);
        }

        return $html;
    }
}
