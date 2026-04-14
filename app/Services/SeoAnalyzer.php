<?php

namespace App\Services;

use App\Models\Page;
use App\Models\SeoIssue;
use App\Models\Site;
use Illuminate\Support\Facades\File;

class SeoAnalyzer
{
    /**
     * Analyze a page and return score + suggestions.
     *
     * @return array{score: int, suggestions: array<array{severity: string, message: string, field: string}>}
     */
    public function analyze(Page $page, ?string $focusKeyword = null): array
    {
        $suggestions = [];
        $score = 0;
        $maxScore = 120;
        $insights = $this->sourceInsights($page);

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
        $contentResult = $this->checkContent($page, $insights);
        $score += $contentResult['points'];
        $suggestions = array_merge($suggestions, $contentResult['suggestions']);

        // Readability & keyword targeting (10 points)
        $readabilityResult = $this->checkReadabilityAndKeyword($page, $focusKeyword);
        $score += $readabilityResult['points'];
        $suggestions = array_merge($suggestions, $readabilityResult['suggestions']);

        // Technical hygiene (10 points)
        $technicalResult = $this->checkTechnicalHygiene($page, $insights);
        $score += $technicalResult['points'];
        $suggestions = array_merge($suggestions, $technicalResult['suggestions']);

        $score = min($maxScore, max(0, $score));
        $normalizedScore = (int) round(($score / $maxScore) * 100);

        // Update the page's SEO score
        $page->update(['seo_score' => $normalizedScore]);

        $this->syncSeoIssuesFromSuggestions($page, $suggestions);

        return [
            'score' => $normalizedScore,
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

    /**
     * Persist analyzer output as open {@see SeoIssue} rows (one per logical field).
     *
     * @param  array<int, array{severity: string, message: string, field: string}>  $suggestions
     */
    private function syncSeoIssuesFromSuggestions(Page $page, array $suggestions): void
    {
        $severityRank = ['error' => 3, 'warning' => 2, 'info' => 1];

        $byField = [];
        foreach ($suggestions as $row) {
            $field = $row['field'] ?? 'general';
            if (! isset($byField[$field])) {
                $byField[$field] = ['severities' => [], 'messages' => []];
            }
            $byField[$field]['severities'][] = $row['severity'] ?? 'warning';
            $byField[$field]['messages'][] = $row['message'];
        }

        $activeCodes = [];

        foreach ($byField as $field => $data) {
            $code = 'analyzer:'.$field;
            $activeCodes[] = $code;

            $severity = collect($data['severities'])
                ->sortByDesc(fn ($s) => $severityRank[$s] ?? 0)
                ->first() ?? 'warning';

            $severity = $this->normalizeIssueSeverity((string) $severity);

            $message = implode(' ', array_unique($data['messages']));

            SeoIssue::query()->updateOrCreate(
                [
                    'page_id' => $page->id,
                    'code' => $code,
                ],
                [
                    'site_id' => $page->site_id,
                    'severity' => $severity,
                    'message' => $message,
                    'meta' => ['field' => $field, 'source' => 'seo_analyzer'],
                    'resolved_at' => null,
                ],
            );
        }

        $prefix = 'analyzer:';
        $prefixLen = strlen($prefix);
        $activeFields = array_keys($byField);

        SeoIssue::query()
            ->where('page_id', $page->id)
            ->whereNull('resolved_at')
            ->where('code', 'like', $prefix.'%')
            ->get()
            ->each(function (SeoIssue $issue) use ($activeFields, $prefixLen): void {
                $field = substr((string) $issue->code, $prefixLen);
                if (! in_array($field, $activeFields, true)) {
                    $issue->update(['resolved_at' => now()]);
                }
            });
    }

    private function normalizeIssueSeverity(string $severity): string
    {
        return match ($severity) {
            'error' => 'error',
            'info' => 'info',
            default => 'warning',
        };
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
            if (! str_starts_with($page->canonical_url, 'http://') && ! str_starts_with($page->canonical_url, 'https://')) {
                $suggestions[] = [
                    'severity' => 'warning',
                    'message' => 'Canonical URL should be absolute and include http/https.',
                    'field' => 'canonical_url',
                ];

                return ['points' => 5, 'suggestions' => $suggestions];
            }

            return ['points' => 10, 'suggestions' => []];
        }

        $suggestions[] = ['severity' => 'info', 'message' => 'No canonical URL set. Recommended to prevent duplicate content issues.', 'field' => 'canonical_url'];

        return ['points' => 3, 'suggestions' => $suggestions];
    }

    private function checkSchema(Page $page): array
    {
        $suggestions = [];

        if (! empty($page->schema_json)) {
            if (is_array($page->schema_json) && empty($page->schema_json['@type'] ?? null)) {
                $suggestions[] = [
                    'severity' => 'warning',
                    'message' => 'Schema exists but is missing @type. Add a specific schema type (Article, Product, FAQPage, etc).',
                    'field' => 'schema_json',
                ];

                return ['points' => 6, 'suggestions' => $suggestions];
            }

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

        if (str_contains($path, '_') || preg_match('/[A-Z]/', $path)) {
            $suggestions[] = [
                'severity' => 'info',
                'message' => 'Use lowercase words and hyphens in URLs for better readability.',
                'field' => 'url_path',
            ];
            $points = max(0, $points - 1);
        }

        return ['points' => $points, 'suggestions' => $suggestions];
    }

    private function checkContent(Page $page, array $insights): array
    {
        $suggestions = [];

        if (! $page->content_hash) {
            $suggestions[] = ['severity' => 'warning', 'message' => 'Page content could not be analyzed.', 'field' => 'content'];

            return ['points' => 0, 'suggestions' => $suggestions];
        }

        $wordCount = $insights['word_count'] ?? 0;
        if ($wordCount < 150) {
            $suggestions[] = [
                'severity' => 'warning',
                'message' => "Page appears thin ({$wordCount} words). Add more useful content for stronger rankings.",
                'field' => 'content',
            ];

            return ['points' => 4, 'suggestions' => $suggestions];
        }

        if ($wordCount < 300) {
            $suggestions[] = [
                'severity' => 'info',
                'message' => "Content depth is moderate ({$wordCount} words). Expanding can improve topical authority.",
                'field' => 'content',
            ];

            return ['points' => 7, 'suggestions' => $suggestions];
        }

        return ['points' => 10, 'suggestions' => []];
    }

    private function checkReadabilityAndKeyword(Page $page, ?string $focusKeyword = null): array
    {
        $suggestions = [];
        $points = 6;

        $title = trim((string) $page->title);
        $description = trim((string) $page->meta_description);
        $keywords = collect(explode(',', (string) $page->meta_keywords))
            ->map(fn (string $keyword) => trim(mb_strtolower($keyword)))
            ->filter()
            ->values();
        $focusKeyword = trim(mb_strtolower((string) $focusKeyword));

        if ($focusKeyword !== '') {
            $keywords = $keywords->prepend($focusKeyword)->unique()->values();
        }

        if ($title !== '' && preg_match('/[|>\-]/', $title)) {
            $points += 1;
        } else {
            $suggestions[] = [
                'severity' => 'info',
                'message' => 'Title can often perform better with a clear separator (e.g., "Service | Brand").',
                'field' => 'title_format',
            ];
        }

        if ($keywords->isNotEmpty()) {
            $keywordHits = $keywords->filter(function (string $keyword) use ($title, $description): bool {
                return str_contains(mb_strtolower($title), $keyword)
                    || str_contains(mb_strtolower($description), $keyword);
            })->count();

            if ($keywordHits === 0) {
                $suggestions[] = [
                    'severity' => 'warning',
                    'message' => 'Primary keywords are not reflected in title/description. Include at least one naturally.',
                    'field' => 'meta_keywords',
                ];
                $points -= 3;
            } elseif ($keywordHits >= 1) {
                $points += 2;
            }
        } else {
            $suggestions[] = [
                'severity' => 'info',
                'message' => 'Add one or two primary keywords to guide copy optimization.',
                'field' => 'meta_keywords',
            ];
        }

        if ($focusKeyword !== '') {
            $focusInTitle = str_contains(mb_strtolower($title), $focusKeyword);
            $focusInDescription = str_contains(mb_strtolower($description), $focusKeyword);

            if (! $focusInTitle && ! $focusInDescription) {
                $suggestions[] = [
                    'severity' => 'warning',
                    'message' => "Focus keyword \"{$focusKeyword}\" is missing from title and description.",
                    'field' => 'focus_keyword',
                ];
                $points -= 2;
            } elseif ($focusInTitle && $focusInDescription) {
                $points += 1;
            }
        }

        if ($description !== '' && mb_strlen($description) > 0) {
            $sentenceCount = max(1, preg_match_all('/[.!?]+/', $description));
            $wordCount = max(1, count(preg_split('/\s+/', $description, -1, PREG_SPLIT_NO_EMPTY) ?: []));
            $avgSentenceLength = $wordCount / $sentenceCount;

            if ($avgSentenceLength > 22) {
                $suggestions[] = [
                    'severity' => 'info',
                    'message' => 'Meta description reads long. Shorter sentences are easier to scan in SERPs.',
                    'field' => 'meta_description',
                ];
                $points -= 1;
            } else {
                $points += 1;
            }
        }

        return ['points' => max(0, min(10, $points)), 'suggestions' => $suggestions];
    }

    private function checkTechnicalHygiene(Page $page, array $insights): array
    {
        $suggestions = [];
        $points = 6;

        if ($page->canonical_url && $page->site?->domain) {
            $siteDomain = mb_strtolower((string) $page->site->domain);
            $canonicalHost = parse_url((string) $page->canonical_url, PHP_URL_HOST);
            if ($canonicalHost && ! str_contains(mb_strtolower($canonicalHost), $siteDomain)) {
                $suggestions[] = [
                    'severity' => 'warning',
                    'message' => 'Canonical URL points to a different domain. Confirm this is intentional.',
                    'field' => 'canonical_url',
                ];
                $points -= 2;
            }
        }

        if ($page->og_image) {
            if (! str_starts_with($page->og_image, 'http://') && ! str_starts_with($page->og_image, 'https://')) {
                $suggestions[] = [
                    'severity' => 'warning',
                    'message' => 'Social image should use an absolute URL for reliable previews.',
                    'field' => 'og_image',
                ];
                $points -= 2;
            } else {
                $points += 2;
            }
        }

        $h1Count = (int) ($insights['h1_count'] ?? 0);
        if ($h1Count === 0) {
            $suggestions[] = [
                'severity' => 'warning',
                'message' => 'No H1 heading found in page source. Add one clear primary heading.',
                'field' => 'content',
            ];
            $points -= 2;
        } elseif ($h1Count > 1) {
            $suggestions[] = [
                'severity' => 'info',
                'message' => "Multiple H1 tags detected ({$h1Count}). Prefer one primary H1 per page.",
                'field' => 'content',
            ];
            $points -= 1;
        } else {
            $points += 1;
        }

        $missingAlt = (int) ($insights['img_missing_alt'] ?? 0);
        if ($missingAlt > 0) {
            $suggestions[] = [
                'severity' => 'warning',
                'message' => "Found {$missingAlt} image(s) without alt text. Add descriptive alts for accessibility and image SEO.",
                'field' => 'content',
            ];
            $points -= 2;
        } elseif (($insights['img_count'] ?? 0) > 0) {
            $points += 1;
        }

        if (($insights['internal_link_count'] ?? 0) === 0) {
            $suggestions[] = [
                'severity' => 'info',
                'message' => 'No internal links detected on this page. Add links to related pages for crawl depth.',
                'field' => 'content',
            ];
            $points -= 1;
        }

        if ($page->og_title && $page->title && mb_strtolower(trim($page->og_title)) === mb_strtolower(trim($page->title))) {
            $suggestions[] = [
                'severity' => 'info',
                'message' => 'og:title matches SEO title exactly. Consider variant copy for better social CTR.',
                'field' => 'og_title',
            ];
        }

        if (! $page->schema_json) {
            $points -= 1;
        } else {
            $points += 1;
        }

        return ['points' => max(0, min(10, $points)), 'suggestions' => $suggestions];
    }

    /**
     * @return array{word_count:int,h1_count:int,img_count:int,img_missing_alt:int,internal_link_count:int}
     */
    private function sourceInsights(Page $page): array
    {
        $defaults = [
            'word_count' => 0,
            'h1_count' => 0,
            'img_count' => 0,
            'img_missing_alt' => 0,
            'internal_link_count' => 0,
        ];

        $site = $page->site;
        if (! $site?->repo_path || ! $page->file_path) {
            return $defaults;
        }

        $fullPath = "{$site->repo_path}/{$page->file_path}";
        if (! File::exists($fullPath)) {
            return $defaults;
        }

        $source = (string) File::get($fullPath);
        if ($source === '') {
            return $defaults;
        }

        $withoutScripts = preg_replace('/<script\b[^>]*>.*?<\/script>/is', ' ', $source) ?? $source;
        $withoutStyles = preg_replace('/<style\b[^>]*>.*?<\/style>/is', ' ', $withoutScripts) ?? $withoutScripts;

        $text = trim(strip_tags($withoutStyles));
        $wordCount = count(preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY) ?: []);
        $h1Count = preg_match_all('/<h1\b[^>]*>/i', $withoutStyles) ?: 0;
        $imgCount = preg_match_all('/<img\b[^>]*>/i', $withoutStyles) ?: 0;
        $imgMissingAlt = preg_match_all('/<img\b(?![^>]*\balt\s*=)[^>]*>/i', $withoutStyles) ?: 0;

        $internalLinks = 0;
        if (preg_match_all('/<a\b[^>]*href=["\']([^"\']+)["\'][^>]*>/i', $withoutStyles, $matches)) {
            foreach ($matches[1] as $href) {
                if (str_starts_with($href, '/')) {
                    $internalLinks++;
                }
            }
        }

        return [
            'word_count' => $wordCount,
            'h1_count' => $h1Count,
            'img_count' => $imgCount,
            'img_missing_alt' => $imgMissingAlt,
            'internal_link_count' => $internalLinks,
        ];
    }
}
