<?php

namespace App\Livewire\Editor;

use App\Models\EditableRegion;
use App\Models\Page;
use App\Support\SiteAccess;
use App\Services\ContentPatcher;
use App\Services\RegionDetector;
use App\Services\SiteSupportService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class RegionPanel extends Component
{
    public string $pageId;
    public string $filter = 'all'; // all|dynamic|static|unconfirmed
    public ?string $selectedRegionId = null;

    public function confirmEditable(string $regionId): void
    {
        $region = $this->resolveRegion($regionId);
        $detector = app(RegionDetector::class);

        $markerId = $detector->generateMarkerId($region);
        $detector->confirmAsEditable($region, $markerId);

        $this->dispatch('region-updated', regionId: $regionId)->to(VisualEditor::class);
    }

    public function confirmStatic(string $regionId): void
    {
        $region = $this->resolveRegion($regionId);
        app(RegionDetector::class)->confirmAsStatic($region);

        $this->dispatch('region-updated', regionId: $regionId)->to(VisualEditor::class);
    }

    public function toggleRegion(string $regionId): void
    {
        $region = $this->resolveRegion($regionId);

        if ($region->is_static) {
            $this->confirmEditable($regionId);
        } else {
            $this->confirmStatic($regionId);
        }
    }

    public function selectRegion(string $regionId): void
    {
        $this->selectedRegionId = $regionId;
        $this->dispatch('region-selected', regionId: $regionId)->to(VisualEditor::class);
    }

    #[On('region-selected')]
    public function syncSelectedRegion(string $regionId): void
    {
        $this->selectedRegionId = $regionId;
    }

    public function render(): View
    {
        $page = Page::query()
            ->with('site')
            ->whereKey($this->pageId)
            ->whereHas('site', fn ($query) => $query->visibleTo(auth()->user()))
            ->firstOrFail();
        $patcher = app(ContentPatcher::class);
        $support = app(SiteSupportService::class);
        $editorProfile = $support->editorProfile($page->site, $page);

        $query = $page->editableRegions();

        $query = match ($this->filter) {
            'dynamic'     => $query->where('is_static', false),
            'static'      => $query->where('is_static', true),
            'unconfirmed' => $query->where('detection_method', 'auto'),
            default       => $query,
        };

        $regions = $query->get()
            ->sortBy(function (EditableRegion $region) {
                return $this->regionSortKey($region);
            })
            ->values();

        $regionTags = $regions
            ->mapWithKeys(fn (EditableRegion $region) => [
                $region->id => $this->extractHtmlTagFromSelector($region->selector),
            ])
            ->all();

        $byAnchor = $regions->groupBy(
            fn (EditableRegion $region) => $this->mainAnchorKey((string) ($region->selector ?? ''))
        );

        $groupOrder = [];
        foreach ($regions as $region) {
            $anchorKey = $this->mainAnchorKey((string) ($region->selector ?? ''));
            if (! in_array($anchorKey, $groupOrder, true)) {
                $groupOrder[] = $anchorKey;
            }
        }

        $layerGroups = [];
        foreach ($groupOrder as $anchorKey) {
            /** @var \Illuminate\Support\Collection<int, EditableRegion> $groupRegions */
            $groupRegions = $byAnchor->get($anchorKey, collect())->sortBy(
                fn (EditableRegion $r) => $this->regionSortKey($r)
            )->values();
            $firstSelector = (string) ($groupRegions->first()?->selector ?? '');
            $layerGroups[] = [
                'key'       => $anchorKey,
                'label'     => $this->mainAnchorLabel($firstSelector),
                'token'     => $this->mainAnchorTagToken($firstSelector),
                'regions'   => $groupRegions->all(),
            ];
        }

        $visualEditability = $regions
            ->mapWithKeys(fn (EditableRegion $region) => [
                $region->id => $editorProfile['visual_editing_supported'] && $patcher->canVisuallyEditRegion($region),
            ])
            ->all();

        $counts = [
            'all'         => $page->editableRegions()->count(),
            'dynamic'     => $page->editableRegions()->where('is_static', false)->count(),
            'static'      => $page->editableRegions()->where('is_static', true)->count(),
            'unconfirmed' => $page->editableRegions()->where('detection_method', 'auto')->count(),
        ];
        $visualEditableCount = $editorProfile['visual_editing_supported']
            ? $page->editableRegions()
                ->get()
                ->filter(fn (EditableRegion $region) => $patcher->canVisuallyEditRegion($region))
                ->count()
            : 0;

        return view('livewire.editor.region-panel', [
            'regions'       => $regions,
            'layerGroups'   => $layerGroups,
            'counts'        => $counts,
            'visualEditability' => $visualEditability,
            'visualEditableCount' => $visualEditableCount,
            'regionTags' => $regionTags,
            'editorProfile' => $editorProfile,
        ]);
    }

    private function resolveRegion(string $regionId): EditableRegion
    {
        return EditableRegion::query()
            ->whereKey($regionId)
            ->where('page_id', $this->pageId)
            ->firstOrFail();
    }

    private function regionSortKey(EditableRegion $region): int
    {
        $lineStart = data_get($region->source_location, 'line_start');
        if (is_numeric($lineStart) && (int) $lineStart > 0) {
            return (int) $lineStart;
        }

        $selector = (string) $region->selector;
        preg_match_all('/:nth-of-type\((\d+)\)/', $selector, $matches);
        $indexes = array_map(static fn ($value) => (int) $value, $matches[1] ?? []);

        if (! empty($indexes)) {
            $key = 0;
            foreach (array_slice($indexes, 0, 5) as $index) {
                $key = ($key * 100) + $index;
            }

            // Keep selector-derived order after source line order.
            return 100000 + $key;
        }

        return 900000 + ($region->created_at?->getTimestamp() ?? 0);
    }

    private function extractHtmlTagFromSelector(string $selector): string
    {
        $segment = trim((string) str($selector)->explode('>')->last());
        if ($segment === '') {
            return 'div';
        }

        if (preg_match('/^([a-zA-Z][a-zA-Z0-9-]*)/', $segment, $matches) === 1) {
            return strtolower($matches[1]);
        }

        return 'div';
    }

    /**
     * @return list<string>
     */
    private function selectorSegments(string $selector): array
    {
        $path = trim($selector);
        if ($path === '') {
            return [];
        }

        $parts = preg_split('/\s*>\s*/', $path);

        return is_array($parts) ? array_values(array_filter(array_map('trim', $parts))) : [];
    }

    private function mainAnchorKey(string $selector): string
    {
        $segments = $this->selectorSegments($selector);
        if ($segments === []) {
            return '';
        }

        return preg_replace('/\s+/', '', strtolower($segments[0]));
    }

    private function mainAnchorLabel(string $selector): string
    {
        $segments = $this->selectorSegments($selector);
        if ($segments === []) {
            return 'Page root';
        }

        $first = $segments[0];

        if (str_starts_with($first, '#')) {
            return $first;
        }

        $normalized = preg_replace('/\s+/', '', strtolower($first));
        $tagName = preg_replace('/[^a-z0-9_-].*/', '', $normalized);

        return $tagName !== '' ? ucfirst($tagName) : $first;
    }

    private function mainAnchorTagToken(string $selector): string
    {
        $segments = $this->selectorSegments($selector);
        if ($segments === []) {
            return '<root>';
        }

        $first = $segments[0];
        if (str_starts_with($first, '#')) {
            return $first;
        }

        $normalized = preg_replace('/\s+/', '', strtolower($first));
        $tagName = preg_replace('/[^a-z0-9_-].*/', '', $normalized) ?: 'element';

        return '<'.$tagName.'>';
    }
}
