<?php

namespace App\Services;

use App\Models\EditableRegion;
use App\Models\Page;
use App\Models\Site;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Str;
use Symfony\Component\CssSelector\CssSelectorConverter;

class PreviewOverlayService
{
    public function __construct(
        private ?CssSelectorConverter $selectors = null,
    ) {
        $this->selectors ??= new CssSelectorConverter();
    }

    public function decorate(Site $site, Page $page, string $html): string
    {
        if (trim($html) === '') {
            return $html;
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $internalErrors = libxml_use_internal_errors(true);

        try {
            $loaded = $document->loadHTML(
                '<?xml encoding="utf-8" ?>' . $html,
                LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING,
            );

            if (! $loaded) {
                return $html;
            }

            $regions = $page->relationLoaded('editableRegions')
                ? $page->editableRegions
                : $page->editableRegions()->get();

            $xpath = new DOMXPath($document);

            foreach ($regions as $region) {
                $this->decorateRegionNodes($xpath, $region);
            }

            $this->decorateDocumentShell($document, $site, $page);

            $rendered = $document->saveHTML();

            return preg_replace('/^<\?xml[^>]+>\s*/', '', $rendered ?? '') ?: $html;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($internalErrors);
        }
    }

    private function decorateRegionNodes(DOMXPath $xpath, EditableRegion $region): void
    {
        $matches = $this->matchRegionNodes($xpath, $region);
        $index = 1;

        foreach ($matches as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $existingRegionId = $node->getAttribute('data-pk-region-id');
            if ($existingRegionId !== '' && $existingRegionId !== $region->id) {
                continue;
            }

            $node->setAttribute('data-pk-region', '');
            $node->setAttribute('data-pk-region-id', (string) $region->id);
            $node->setAttribute('data-pk-node-id', $this->temporaryNodeId($region, $index));
            $node->setAttribute('data-pk-region-type', (string) $region->region_type);
            $node->setAttribute('data-pk-editable', $this->isVisualEditable($region) ? 'true' : 'false');
            $node->setAttribute('data-pk-region-role', $this->isVisualEditable($region) ? 'editable' : 'code');
            $node->setAttribute('data-pk-region-label', $this->regionLabel($region));

            $index++;
        }
    }

    /**
     * @return list<DOMElement>
     */
    private function matchRegionNodes(DOMXPath $xpath, EditableRegion $region): array
    {
        $selectors = array_values(array_unique(array_filter([
            $region->render_selector,
            $region->selector,
            $region->marker_id ? '[data-cms-id="' . $region->marker_id . '"]' : null,
        ])));

        foreach ($selectors as $selector) {
            $nodes = $this->queryCss($xpath, $selector);
            if ($nodes !== []) {
                return $nodes;
            }
        }

        $content = trim(strip_tags((string) $region->current_content));

        if ($content === '') {
            return [];
        }

        $best = null;
        $bestScore = 0.0;
        foreach ($this->textCandidateNodes($xpath) as $node) {
            $nodeText = $this->normalizedText($node->textContent ?? '');
            if ($nodeText === '') {
                continue;
            }

            $score = $this->contentScore($nodeText, $content, $region);
            if ($score > $bestScore) {
                $best = $node;
                $bestScore = $score;
            }
        }

        return $best && $bestScore >= 0.72 ? [$best] : [];
    }

    /**
     * @return list<DOMElement>
     */
    private function queryCss(DOMXPath $xpath, string $selector): array
    {
        try {
            $expression = $this->selectors->toXPath($selector);
            $nodes = $xpath->query($expression);

            if ($nodes === false) {
                return [];
            }

            $matches = [];
            foreach ($nodes as $node) {
                if ($node instanceof DOMElement) {
                    $matches[] = $node;
                }
            }

            return $matches;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return list<DOMElement>
     */
    private function textCandidateNodes(DOMXPath $xpath): array
    {
        $nodes = $xpath->query('//h1|//h2|//h3|//h4|//h5|//h6|//p|//a|//button|//li|//label|//blockquote|//figcaption|//span|//div|//section|//article');

        if ($nodes === false) {
            return [];
        }

        $matches = [];
        foreach ($nodes as $node) {
            if ($node instanceof DOMElement) {
                $matches[] = $node;
            }
        }

        return $matches;
    }

    private function contentScore(string $candidateText, string $content, EditableRegion $region): float
    {
        $needle = $this->normalizedText($content);
        $score = 0.0;

        if ($candidateText === $needle) {
            $score += 1.0;
        } elseif (str_contains($candidateText, $needle) || str_contains($needle, $candidateText)) {
            $score += 0.84;
        } else {
            $score += $this->tokenOverlap($candidateText, $needle);
        }

        $expectedTag = strtolower((string) data_get($region->dom_fingerprint, 'tag'));
        if ($expectedTag !== '') {
            $score += match ($expectedTag) {
                'h1', 'h2', 'h3', 'h4', 'h5', 'h6' => 0.08,
                'p', 'a', 'button', 'li', 'section', 'article', 'div', 'span' => 0.04,
                default => 0.0,
            };
        }

        return min(1.0, $score);
    }

    private function tokenOverlap(string $candidate, string $needle): float
    {
        $candidateTokens = collect(explode(' ', $candidate))
            ->filter()
            ->values()
            ->all();
        $needleTokens = collect(explode(' ', $needle))
            ->filter()
            ->values()
            ->all();

        if ($candidateTokens === [] || $needleTokens === []) {
            return 0.0;
        }

        $candidateLookup = array_fill_keys($candidateTokens, true);
        $overlap = 0;

        foreach ($needleTokens as $token) {
            if (isset($candidateLookup[$token])) {
                $overlap++;
            }
        }

        return $overlap / max(count($candidateTokens), count($needleTokens));
    }

    private function decorateDocumentShell(DOMDocument $document, Site $site, Page $page): void
    {
        $html = $document->getElementsByTagName('html')->item(0);
        if ($html instanceof DOMElement) {
            $html->setAttribute('data-pk-preview', 'editor');
            $html->setAttribute('data-pk-site-id', (string) $site->id);
            $html->setAttribute('data-pk-page-id', (string) $page->id);
        }

        $body = $document->getElementsByTagName('body')->item(0);
        if ($body instanceof DOMElement) {
            $body->setAttribute('data-pk-preview-mode', 'editor');
            $body->setAttribute('data-pk-page-path', (string) ($page->url_path ?: '/'));
        }

        $head = $document->getElementsByTagName('head')->item(0);

        if (! $head instanceof DOMElement) {
            return;
        }

        if ($document->getElementById('pk-preview-meta')) {
            return;
        }

        $meta = $document->createElement('meta');
        $meta->setAttribute('id', 'pk-preview-meta');
        $meta->setAttribute('name', 'pixelkraft-preview');
        $meta->setAttribute('content', 'editor');

        $head->appendChild($meta);
    }

    private function normalizedText(string $text): string
    {
        return Str::of($text)
            ->replaceMatches('/\s+/u', ' ')
            ->trim()
            ->lower()
            ->value();
    }

    private function regionLabel(EditableRegion $region): string
    {
        $label = trim(strip_tags((string) $region->current_content));

        if ($label !== '') {
            return Str::limit($label, 120, '');
        }

        return (string) $region->region_type;
    }

    private function isVisualEditable(EditableRegion $region): bool
    {
        if ($region->region_type === 'image') {
            return true;
        }

        return $region->hasVerifiedAnchor() || $region->isConfirmed() || $region->hasHighConfidence();
    }

    private function temporaryNodeId(EditableRegion $region, int $index): string
    {
        return 'pk-node-' . Str::slug((string) $region->id, '-') . '-' . $index;
    }
}
