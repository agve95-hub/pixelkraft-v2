<?php

namespace App\Services;

use App\Models\EditableRegion;
use App\Models\Page;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

class ContentPatcher
{
    /**
     * @var array<string, string>
     */
    private array $sourceContentCache = [];

    /**
     * Apply a content edit to the source file and return the list of changed files.
     *
     * @return string[] List of modified file paths (relative to repo root)
     */
    public function applyEdit(EditableRegion $region, string $newContent): array
    {
        $page = $region->page;
        $site = $page->site;
        $repoPath = $site->repo_path;

        if (! app(SiteSupportService::class)->supportsVisualEditing($site, $page)) {
            throw new \RuntimeException('Visual save is disabled for this page type. Use Code mode to edit the source safely.');
        }

        $sourceLocation = $region->source_location;
        $targetFile = $sourceLocation['file'] ?? $page->file_path;
        $sourceType = $sourceLocation['source_type'] ?? 'html';
        $fullPath = "{$repoPath}/{$targetFile}";

        if (! File::exists($fullPath)) {
            throw new \RuntimeException("Source file not found: {$targetFile}");
        }

        $originalContent = File::get($fullPath);
        $changedFiles = [];
        $contentChanged = trim((string) $region->current_content) !== trim($newContent);

        $patched = match ($sourceType) {
            'html', 'template' => $this->patchHtml($originalContent, $region, $newContent),
            'markdown'         => $this->patchMarkdown($originalContent, $region, $newContent),
            'component',
            'component_preview',
            'component_runtime_preview' => $this->patchComponent($originalContent, $region, $newContent),
            default => $this->patchHtml($originalContent, $region, $newContent),
        };

        if ($patched !== $originalContent) {
            File::put($fullPath, $patched);
            $changedFiles[] = $targetFile;

            Log::info("Patched [{$targetFile}] for region [{$region->selector}]");
        }
        // Do not silently accept a "save" when no source edit was applied.
        if ($patched === $originalContent && $contentChanged) {
            throw new \RuntimeException('pixelkraft could not map this edit back to source code safely. Try a smaller element or switch to Code mode.');
        }

        // Update region snapshot only after a successful source update (or true no-op).
        $region->update(['current_content' => $newContent]);

        return $changedFiles;
    }

    public function canVisuallyEditRegion(EditableRegion $region): bool
    {
        if ($region->is_static) {
            return false;
        }

        $page = $region->page;
        $site = $page->site;

        if (! app(SiteSupportService::class)->supportsVisualEditing($site, $page)) {
            return false;
        }

        $sourceLocation = $region->source_location ?? [];
        $targetFile = $sourceLocation['file'] ?? $page->file_path;
        $sourceType = $sourceLocation['source_type'] ?? 'html';
        $fullPath = "{$site->repo_path}/{$targetFile}";

        if (! File::exists($fullPath)) {
            return false;
        }

        if (in_array($sourceType, ['html', 'template', 'markdown'], true)) {
            return true;
        }

        if (! in_array($sourceType, ['component', 'component_preview', 'component_runtime_preview'], true)) {
            return false;
        }

        return $this->canPatchComponentRegion($this->getSourceContent($fullPath), $region);
    }

    /**
     * Apply multiple edits at once (batch save).
     *
     * @param array<string, string> $edits  [region_id => new_content]
     * @return string[] List of all changed files
     */
    public function applyBatch(array $edits): array
    {
        $changedFiles = [];

        foreach ($edits as $regionId => $newContent) {
            $region = EditableRegion::find($regionId);

            if (! $region) {
                continue;
            }

            $files = $this->applyEdit($region, $newContent);
            $changedFiles = array_merge($changedFiles, $files);
        }

        return array_unique($changedFiles);
    }

    // ── HTML Patching ───────────────────────────

    private function patchHtml(string $html, EditableRegion $region, string $newContent): string
    {
        // Strategy 1: If region has a marker, replace content between markers
        if ($region->marker_id) {
            return $this->patchByMarker($html, $region->marker_id, $newContent);
        }

        // Strategy 2: Replace by CSS selector
        return $this->patchBySelector($html, $region, $newContent);
    }

    /**
     * Replace content between cms:editable markers.
     */
    private function patchByMarker(string $html, string $markerId, string $newContent): string
    {
        $escapedId = preg_quote($markerId, '/');

        $pattern = '/(<!--\s*cms:editable\s+id="' . $escapedId . '"[^>]*-->)\s*(.*?)\s*(<!--\s*\/cms:editable\s*-->)/s';

        return preg_replace($pattern, "$1\n{$newContent}\n$3", $html, 1);
    }

    /**
     * Replace content by finding the element matching the region's selector.
     */
    private function patchBySelector(string $html, EditableRegion $region, string $newContent): string
    {
        $oldContent = $region->current_content;

        if (empty($oldContent)) {
            return $html;
        }

        $selector = $region->selector;
        $type = $region->region_type;

        // For text regions: replace the text content of the element
        if ($type === 'text') {
            return $this->replaceTextContent($html, $selector, $oldContent, $newContent);
        }

        // For image regions: replace the src attribute
        if ($type === 'image') {
            return $this->replaceImageSrc($html, $oldContent, $newContent);
        }

        // For link regions: replace href or text
        if ($type === 'link') {
            return $this->replaceLinkContent($html, $oldContent, $newContent);
        }

        // Fallback: simple string replacement
        return $this->safeReplace($html, $oldContent, $newContent);
    }

    /**
     * Replace text content within an element, preserving tags.
     */
    private function replaceTextContent(string $html, string $selector, string $oldText, string $newText): string
    {
        // Try DOM-based replacement first
        try {
            $crawler = new Crawler($html);
            $node = $crawler->filter($selector);

            if ($node->count() > 0) {
                $oldNodeHtml = $node->outerHtml();
                $newNodeHtml = str_replace(
                    trim(strip_tags($oldNodeHtml)),
                    $newText,
                    $oldNodeHtml
                );

                // Only replace if we actually changed something
                if ($oldNodeHtml !== $newNodeHtml) {
                    return str_replace($oldNodeHtml, $newNodeHtml, $html);
                }
            }
        } catch (\Throwable $e) {
            // Fall through to simpler replacement
        }

        // Fallback: direct text replacement
        return $this->safeReplace($html, $oldText, $newText);
    }

    /**
     * Replace an image src attribute.
     */
    private function replaceImageSrc(string $html, string $oldSrc, string $newSrc): string
    {
        $escapedOld = preg_quote($oldSrc, '/');

        // Replace in src="..." attribute
        return preg_replace(
            '/src=(["\'])' . $escapedOld . '\1/',
            'src=$1' . $newSrc . '$1',
            $html,
            1
        );
    }

    /**
     * Replace link content (href or visible text).
     */
    private function replaceLinkContent(string $html, string $oldContent, string $newContent): string
    {
        // If the new content looks like a URL, replace href
        if (filter_var($newContent, FILTER_VALIDATE_URL)) {
            $escapedOld = preg_quote($oldContent, '/');

            return preg_replace(
                '/href=(["\'])' . $escapedOld . '\1/',
                'href=$1' . $newContent . '$1',
                $html,
                1
            );
        }

        // Otherwise replace the visible text
        return $this->safeReplace($html, $oldContent, $newContent);
    }

    // ── Markdown Patching ───────────────────────

    private function patchMarkdown(string $content, EditableRegion $region, string $newContent): string
    {
        $oldContent = $region->current_content;

        if (empty($oldContent)) {
            return $content;
        }

        // For markdown, the content is usually plain text
        return $this->safeReplace($content, $oldContent, $newContent);
    }

    // ── Component Patching ──────────────────────

    private function patchComponent(string $content, EditableRegion $region, string $newContent): string
    {
        return match ($region->region_type) {
            'image' => $this->patchComponentImage($content, $region, $newContent),
            'link' => $this->patchComponentLink($content, $region, $newContent),
            default => $this->patchComponentText($content, $region, $newContent),
        };
    }

    // ── Helpers ──────────────────────────────────

    private function canPatchComponentRegion(string $content, EditableRegion $region): bool
    {
        return match ($region->region_type) {
            'image' => $this->resolveComponentImageStrategy($content, $region) !== null,
            'link' => $this->resolveComponentTextStrategy($content, $region) !== null,
            default => $this->resolveComponentTextStrategy($content, $region) !== null,
        };
    }

    private function patchComponentText(string $content, EditableRegion $region, string $newContent): string
    {
        $strategy = $this->resolveComponentTextStrategy($content, $region);

        if (! $strategy) {
            throw new \RuntimeException('pixelkraft could not safely map this rendered text back to a unique source string. Use Code mode for this region.');
        }

        return substr_replace($content, $newContent, $strategy['start'], $strategy['length']);
    }

    private function patchComponentImage(string $content, EditableRegion $region, string $newContent): string
    {
        $strategy = $this->resolveComponentImageStrategy($content, $region);

        if (! $strategy) {
            throw new \RuntimeException('pixelkraft could not safely map this image back to a unique source attribute. Use Code mode for this region.');
        }

        return preg_replace_callback(
            $strategy['pattern'],
            fn (array $matches) => $matches[1] . $newContent . $matches[2],
            $content,
            1
        ) ?? $content;
    }

    private function patchComponentLink(string $content, EditableRegion $region, string $newContent): string
    {
        return $this->patchComponentText($content, $region, $newContent);
    }

    /**
     * @return array{kind: string, start: int, length: int}|null
     */
    private function resolveComponentTextStrategy(string $content, EditableRegion $region): ?array
    {
        $pattern = $this->buildFlexibleTextPattern((string) $region->current_content);

        if ($pattern === null) {
            return null;
        }

        $jsxMatches = $this->findCapturedMatches(
            '/(?:>\\s*|\\}\\s*)(' . $pattern . ')(\\s*)(?=(?:<|\\{))/u',
            $content,
            1,
        );
        $expectedTag = $this->expectedTagName($region);

        if ($expectedTag !== null) {
            $jsxMatches = array_values(array_filter($jsxMatches, function (array $match) use ($content, $expectedTag) {
                return $this->nearestOpeningTagName($content, $match['start']) === $expectedTag;
            }));
        }

        if (count($jsxMatches) === 1) {
            return [
                'kind' => 'jsx_text',
                'start' => $jsxMatches[0]['start'],
                'length' => $jsxMatches[0]['length'],
            ];
        }

        $quotedMatches = $this->findQuotedStringMatches($content, $pattern);

        if (count($quotedMatches) === 1) {
            return [
                'kind' => 'quoted_string',
                'start' => $quotedMatches[0]['start'],
                'length' => $quotedMatches[0]['length'],
            ];
        }

        return null;
    }

    /**
     * @return array{kind: string, pattern: string}|null
     */
    private function resolveComponentImageStrategy(string $content, EditableRegion $region): ?array
    {
        $currentContent = trim((string) $region->current_content);

        if ($currentContent === '') {
            return null;
        }

        $escaped = preg_quote($currentContent, '/');
        $srcPattern = '/(src\\s*=\\s*["\\\'])' . $escaped . '(["\\\'])/u';

        if (preg_match_all($srcPattern, $content) === 1) {
            return ['kind' => 'image_src', 'pattern' => $srcPattern];
        }

        return null;
    }

    private function buildFlexibleTextPattern(string $value): ?string
    {
        $tokens = $this->normalizeTextTokens($value);

        if (empty($tokens)) {
            return null;
        }

        $separator = '(?:\\s+|\\s*\\{\\s*[\'"]\\s+[\'"]\\s*\\}\\s*)+';
        $pattern = implode($separator, array_map(function (string $token) {
            $escaped = preg_quote($token, '/');

            return str_replace('\\&', '(?:&|&amp;)', $escaped);
        }, $tokens));

        return $pattern;
    }

    /**
     * @return list<string>
     */
    private function normalizeTextTokens(string $value): array
    {
        $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $normalized = preg_replace('/\\s+/u', ' ', $decoded);

        if ($normalized === null) {
            $normalized = preg_replace('/\\s+/', ' ', $decoded);
        }

        $normalized = trim((string) $normalized);

        if ($normalized === '') {
            return [];
        }

        $tokens = preg_split('/\\s+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY);

        if ($tokens === false) {
            $tokens = preg_split('/\\s+/', $normalized, -1, PREG_SPLIT_NO_EMPTY);
        }

        return array_values(array_filter($tokens ?: [], fn (string $token) => $token !== ''));
    }

    /**
     * @return list<array{start: int, length: int}>
     */
    private function findCapturedMatches(string $pattern, string $content, int $group): array
    {
        $matches = [];

        if (! preg_match_all($pattern, $content, $results, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            return $matches;
        }

        foreach ($results as $result) {
            $capture = $result[$group] ?? null;

            if (! is_array($capture) || ($capture[1] ?? -1) < 0) {
                continue;
            }

            $matches[] = [
                'start' => $capture[1],
                'length' => strlen($capture[0]),
            ];
        }

        return $this->uniqueOffsetMatches($matches);
    }

    /**
     * @return list<array{start: int, length: int}>
     */
    private function findQuotedStringMatches(string $content, string $pattern): array
    {
        $ranges = $this->metadataBlockRanges($content);
        $matches = $this->findCapturedMatches('/(["\\\'])(' . $pattern . ')\\1/u', $content, 2);

        return array_values(array_filter($matches, function (array $match) use ($ranges) {
            return ! $this->offsetFallsWithinRanges($match['start'], $ranges);
        }));
    }

    /**
     * @param list<array{start: int, length: int}> $matches
     * @return list<array{start: int, length: int}>
     */
    private function uniqueOffsetMatches(array $matches): array
    {
        $unique = [];

        foreach ($matches as $match) {
            $unique[$match['start'] . ':' . $match['length']] = $match;
        }

        return array_values($unique);
    }

    /**
     * @return list<array{start: int, end: int}>
     */
    private function metadataBlockRanges(string $content): array
    {
        $ranges = [];
        $offset = 0;

        while (($metadataPos = strpos($content, 'export const metadata', $offset)) !== false) {
            $bracePos = strpos($content, '{', $metadataPos);

            if ($bracePos === false) {
                break;
            }

            $depth = 0;
            $length = strlen($content);
            $inString = null;
            $escaped = false;

            for ($cursor = $bracePos; $cursor < $length; $cursor++) {
                $char = $content[$cursor];

                if ($inString !== null) {
                    if ($escaped) {
                        $escaped = false;
                        continue;
                    }

                    if ($char === '\\') {
                        $escaped = true;
                        continue;
                    }

                    if ($char === $inString) {
                        $inString = null;
                    }

                    continue;
                }

                if (in_array($char, ['"', "'", '`'], true)) {
                    $inString = $char;
                    continue;
                }

                if ($char === '{') {
                    $depth++;
                    continue;
                }

                if ($char === '}') {
                    $depth--;

                    if ($depth === 0) {
                        $ranges[] = [
                            'start' => $metadataPos,
                            'end' => $cursor,
                        ];
                        $offset = $cursor + 1;
                        continue 2;
                    }
                }
            }

            break;
        }

        return $ranges;
    }

    /**
     * @param list<array{start: int, end: int}> $ranges
     */
    private function offsetFallsWithinRanges(int $offset, array $ranges): bool
    {
        foreach ($ranges as $range) {
            if ($offset >= $range['start'] && $offset <= $range['end']) {
                return true;
            }
        }

        return false;
    }

    private function expectedTagName(EditableRegion $region): ?string
    {
        $selector = (string) ($region->source_location['selector'] ?? $region->selector ?? '');

        if ($selector === '') {
            return null;
        }

        $segments = preg_split('/\s*>\s*/', $selector);
        $terminal = trim((string) end($segments));

        if ($terminal === '' || str_starts_with($terminal, '#') || str_starts_with($terminal, '.')) {
            return null;
        }

        if (preg_match('/^([a-z][a-z0-9_-]*)/i', $terminal, $matches)) {
            return strtolower($matches[1]);
        }

        return null;
    }

    private function nearestOpeningTagName(string $content, int $offset): ?string
    {
        $prefix = substr($content, 0, $offset);

        if (! preg_match_all('/<([A-Za-z][A-Za-z0-9:_-]*)\\b[^>]*(?<!\\/)>/s', $prefix, $matches)) {
            return null;
        }

        $tag = end($matches[1]);

        return $tag !== false ? strtolower($tag) : null;
    }

    private function getSourceContent(string $fullPath): string
    {
        return $this->sourceContentCache[$fullPath] ??= File::get($fullPath);
    }

    /**
     * Replace first occurrence only, safely.
     */
    private function safeReplace(string $haystack, string $needle, string $replacement): string
    {
        $pos = strpos($haystack, $needle);

        if ($pos === false) {
            // Try with trimmed/normalized content
            $normalizedNeedle = trim(preg_replace('/\s+/', ' ', $needle));
            $normalizedHaystack = preg_replace('/\s+/', ' ', $haystack);

            $pos = strpos($normalizedHaystack, $normalizedNeedle);

            if ($pos === false) {
                Log::warning("ContentPatcher: could not find content to replace", [
                    'needle_preview' => mb_substr($needle, 0, 100),
                ]);

                return $haystack;
            }
        }

        return substr_replace($haystack, $replacement, $pos, strlen($needle));
    }
}
