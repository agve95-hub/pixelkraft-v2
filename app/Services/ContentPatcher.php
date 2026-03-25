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
     * Apply a content edit to the source file and return the list of changed files.
     *
     * @return string[] List of modified file paths (relative to repo root)
     */
    public function applyEdit(EditableRegion $region, string $newContent): array
    {
        $page = $region->page;
        $site = $page->site;
        $repoPath = $site->repo_path;

        $sourceLocation = $region->source_location;
        $targetFile = $sourceLocation['file'] ?? $page->file_path;
        $sourceType = $sourceLocation['source_type'] ?? 'html';
        $fullPath = "{$repoPath}/{$targetFile}";

        if (! File::exists($fullPath)) {
            throw new \RuntimeException("Source file not found: {$targetFile}");
        }

        $originalContent = File::get($fullPath);
        $changedFiles = [];

        $patched = match ($sourceType) {
            'html', 'template' => $this->patchHtml($originalContent, $region, $newContent),
            'markdown'         => $this->patchMarkdown($originalContent, $region, $newContent),
            'component'        => $this->patchComponent($originalContent, $region, $newContent),
            default            => $this->patchHtml($originalContent, $region, $newContent),
        };

        if ($patched !== $originalContent) {
            File::put($fullPath, $patched);
            $changedFiles[] = $targetFile;

            Log::info("Patched [{$targetFile}] for region [{$region->selector}]");
        }

        // Update the region's current content
        $region->update(['current_content' => $newContent]);

        return $changedFiles;
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
        $oldContent = $region->current_content;

        if (empty($oldContent)) {
            return $content;
        }

        // For components, try to replace the text within JSX/template
        $escaped = preg_quote($oldContent, '/');

        // Replace text between > and <
        $patched = preg_replace(
            '/(>)\s*' . $escaped . '\s*(<)/',
            "$1{$newContent}$2",
            $content,
            1
        );

        if ($patched !== $content) {
            return $patched;
        }

        // Try replacing in string props
        $patched = preg_replace(
            '/(["\'])' . $escaped . '\1/',
            "$1{$newContent}$1",
            $content,
            1
        );

        return $patched !== $content ? $patched : $this->safeReplace($content, $oldContent, $newContent);
    }

    // ── Helpers ──────────────────────────────────

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
