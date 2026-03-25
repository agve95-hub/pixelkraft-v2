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
        $region = EditableRegion::findOrFail($regionId);
        $detector = app(RegionDetector::class);

        $markerId = $detector->generateMarkerId($region);
        $detector->confirmAsEditable($region, $markerId);

        $this->dispatch('region-updated', regionId: $regionId);
    }

    public function confirmStatic(string $regionId): void
    {
        $region = EditableRegion::findOrFail($regionId);
        app(RegionDetector::class)->confirmAsStatic($region);

        $this->dispatch('region-updated', regionId: $regionId);
    }

    public function toggleRegion(string $regionId): void
    {
        $region = EditableRegion::findOrFail($regionId);

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
            'regions' => $regions,
            'counts'  => $counts,
        ]);
    }
}
