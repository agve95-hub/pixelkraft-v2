<?php

namespace App\Livewire\Editor;

use App\Models\EditableRegion;
use App\Models\Page;
use App\Services\RegionDetector;
use Livewire\Component;

class RegionPanel extends Component
{
    public string $pageId;
    public string $filter = 'all'; // all|dynamic|static|unconfirmed

    public function confirmEditable(string $regionId): void
    {
        $region = $this->resolveRegion($regionId);
        $detector = app(RegionDetector::class);

        $markerId = $detector->generateMarkerId($region);
        $detector->confirmAsEditable($region, $markerId);

        $this->dispatch('region-updated', regionId: $regionId);
    }

    public function confirmStatic(string $regionId): void
    {
        $region = $this->resolveRegion($regionId);
        app(RegionDetector::class)->confirmAsStatic($region);

        $this->dispatch('region-updated', regionId: $regionId);
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
        $this->dispatch('region-selected', regionId: $regionId);
    }

    public function render()
    {
        $page = Page::findOrFail($this->pageId);
        $extension = strtolower(pathinfo($page->file_path, PATHINFO_EXTENSION));
        $isPreviewOnly = in_array($page->site->project_type, ['nextjs', 'react', 'vue', 'svelte', 'nuxt'], true)
            && ! in_array($extension, ['html', 'htm'], true);

        $query = $page->editableRegions();

        $query = match ($this->filter) {
            'dynamic'     => $query->where('is_static', false),
            'static'      => $query->where('is_static', true),
            'unconfirmed' => $query->where('detection_method', 'auto'),
            default       => $query,
        };

        $regions = $query->orderBy('confidence_score', 'desc')->get();

        $counts = [
            'all'         => $page->editableRegions()->count(),
            'dynamic'     => $page->editableRegions()->where('is_static', false)->count(),
            'static'      => $page->editableRegions()->where('is_static', true)->count(),
            'unconfirmed' => $page->editableRegions()->where('detection_method', 'auto')->count(),
        ];

        return view('livewire.editor.region-panel', [
            'regions'       => $regions,
            'counts'        => $counts,
            'isPreviewOnly' => $isPreviewOnly,
        ]);
    }

    private function resolveRegion(string $regionId): EditableRegion
    {
        return EditableRegion::query()
            ->whereKey($regionId)
            ->where('page_id', $this->pageId)
            ->firstOrFail();
    }
}
