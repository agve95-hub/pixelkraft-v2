<?php

namespace App\Services;

use App\Models\Page;
use App\Models\Site;

class SeoAnalyzer
{
    /**
     * Analyze a page and return score + suggestions.
     *
     * @return array{score: int, suggestions: array<array{severity: string, message: string, field: string}>}
     */
    public function analyze(Page $page): array
    {
        $suggestions = [];
        $score = 0;
        $maxScore = 100;

        // Title (25 points)
        $titleResult = $this->checkTitle($page);
        $score += $titleResult['points'];
        $suggestions = array_merge($suggestions, $titleResult['suggestions']);

        // Meta description (20 points)
        $descResult = $this->checkMetaDescription($page);
        $score += $descResult['points'];
        $suggestions = array_merge($suggestions, $descResult['suggestions']);

        // Open Graph (15 points)
        $ogResult = $this->checkOpenGraph($page);
        $score += $ogResult['points'];
        $suggestions = array_merge($suggestions, $ogResult['suggestions']);

        // Canonical URL (10 points)
        $canonResult = $this->checkCanonical($page);
        $score += $canonResult['points'];
        $suggestions = array_merge($suggestions, $canonResult['suggestions']);

        // Schema.org JSON-LD (10 points)
        $schemaResult = $this->checkSchema($page);
        $score += $schemaResult['points'];
        $suggestions = array_merge($suggestions, $schemaResult['suggestions']);

        // URL structure (10 points)
        $urlResult = $this->checkUrlStructure($page);
        $score += $urlResult['points'];
        $suggestions = array_merge($suggestions, $urlResult['suggestions']);

        // Content (10 points)
        $contentResult = $this->checkContent($page);
        $score += $contentResult['points'];
        $suggestions = array_merge($suggestions, $contentResult['suggestions']);

        $score = min($maxScore, max(0, $score));

        // Update the page's SEO score
        $page->update(['seo_score' => $score]);

        return [
            'score'       => $score,
            'suggestions' => $suggestions,
        ];
    }

    /**
     * Analyze all pages for a site.
     */
    public function analyzeSite(Site $site): array
    {
        $results = [];

        foreach ($site->pages as $page) {
            $results[$page->id] = $this->analyze($page);
        }

        return $results;
    }

    // ── Individual Checks ───────────────────────

    private function checkTitle(Page $page): array
    {
        $suggestions = [];
        $points = 0;

        if (empty($page->title)) {
            $suggestions[] = ['severity' => 'error', 'message' => 'Page has no title tag. Add a unique, descriptive title.', 'field' => 'title'];
            return ['points' => 0, 'suggestions' => $suggestions];
        }

        $len = mb_strlen($page->title);
        $points = 15;

        if ($len < 10) {
            $suggestions[] = ['severity' => 'warning', 'message' => "Title is too short ({$len} chars). Aim for 30-60 characters.", 'field' => 'title'];
        } elseif ($len > 70) {
            $suggestions[] = ['severity' => 'warning', 'message' => "Title is too long ({$len} chars). Google truncates after ~60 characters.", 'field' => 'title'];
            $points = 18;
        } elseif ($len >= 30 && $len <= 60) {
            $points = 25;
        } else {
            $points = 20;
        }

        return ['points' => $points, 'suggestions' => $suggestions];
    }

    private function checkMetaDescription(Page $page): array
    {
        $suggestions = [];
        $points = 0;

        if (empty($page->meta_description)) {
            $suggestions[] = ['severity' => 'error', 'message' => 'No meta description. Add a compelling 120-155 character description.', 'field' => 'meta_description'];
            return ['points' => 0, 'suggestions' => $suggestions];
        }

        $len = mb_strlen($page->meta_description);
        $points = 10;

        if ($len < 50) {
            $suggestions[] = ['severity' => 'warning', 'message' => "Meta description is short ({$len} chars). Aim for 120-155 characters.", 'field' => 'meta_description'];
        } elseif ($len > 160) {
            $suggestions[] = ['severity' => 'info', 'message' => "Meta description may be truncated ({$len} chars). Keep under 155 characters.", 'field' => 'meta_description'];
            $points = 15;
        } elseif ($len >= 120 && $len <= 155) {
            $points = 20;
        } else {
            $points = 15;
        }

        return ['points' => $points, 'suggestions' => $suggestions];
    }

    private function checkOpenGraph(Page $page): array
    {
        $suggestions = [];
        $points = 0;

        if ($page->og_title) {
            $points += 5;
        } else {
            $suggestions[] = ['severity' => 'info', 'message' => 'No og:title. Social shares will use the page title instead.', 'field' => 'og_title'];
        }

        if ($page->og_description) {
            $points += 5;
        } else {
            $suggestions[] = ['severity' => 'info', 'message' => 'No og:description. Social shares will use the meta description.', 'field' => 'og_description'];
        }

        if ($page->og_image) {
            $points += 5;
        } else {
            $suggestions[] = ['severity' => 'warning', 'message' => 'No og:image. Social shares will have no preview image.', 'field' => 'og_image'];
        }

        return ['points' => $points, 'suggestions' => $suggestions];
    }

    private function checkCanonical(Page $page): array
    {
        $suggestions = [];

        if ($page->canonical_url) {
            return ['points' => 10, 'suggestions' => []];
        }

        $suggestions[] = ['severity' => 'info', 'message' => 'No canonical URL set. Recommended to prevent duplicate content issues.', 'field' => 'canonical_url'];

        return ['points' => 3, 'suggestions' => $suggestions];
    }

    private function checkSchema(Page $page): array
    {
        $suggestions = [];

        if (! empty($page->schema_json)) {
            return ['points' => 10, 'suggestions' => []];
        }

        $suggestions[] = ['severity' => 'info', 'message' => 'No structured data (JSON-LD). Adding Schema.org markup can improve rich snippets.', 'field' => 'schema_json'];

        return ['points' => 0, 'suggestions' => $suggestions];
    }

    private function checkUrlStructure(Page $page): array
    {
        $suggestions = [];
        $points = 5;
        $path = $page->url_path ?? '';

        if (empty($path) || $path === '/') {
            return ['points' => 10, 'suggestions' => []];
        }

        // Check for clean URLs (no file extensions)
        if (preg_match('/\.\w+$/', $path)) {
            $suggestions[] = ['severity' => 'info', 'message' => 'URL contains a file extension. Clean URLs (without .html) are preferred.', 'field' => 'url_path'];
        } else {
            $points += 3;
        }

        // Check for reasonable length
        if (mb_strlen($path) > 75) {
            $suggestions[] = ['severity' => 'info', 'message' => 'URL is long. Shorter URLs tend to rank better.', 'field' => 'url_path'];
        } else {
            $points += 2;
        }

        return ['points' => $points, 'suggestions' => $suggestions];
    }

    private function checkContent(Page $page): array
    {
        $suggestions = [];

        if ($page->content_hash) {
            return ['points' => 10, 'suggestions' => []];
        }

        $suggestions[] = ['severity' => 'warning', 'message' => 'Page content could not be analyzed.', 'field' => 'content'];

        return ['points' => 0, 'suggestions' => $suggestions];
    }
}
