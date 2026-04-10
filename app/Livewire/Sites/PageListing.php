<?php

namespace App\Livewire\Sites;

use App\Models\Page;
use App\Support\SiteAccess;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class PageListing extends Component
{
    public string $siteId;
    public string $search = '';
    public string $sortBy = 'url_path';
    public string $sortDir = 'asc';

    public function sort(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDir = 'asc';
        }
    }

    public function render(): View
    {
        $site = SiteAccess::findOrFail($this->siteId);

        $pages = Page::query()
            ->where('site_id', $site->id)
            ->when($this->search, function ($query): void {
                $query->where(function ($nested): void {
                    $nested->where('title', 'like', "%{$this->search}%")
                        ->orWhere('url_path', 'like', "%{$this->search}%");
                });
            })
            ->orderBy($this->sortBy, $this->sortDir)
            ->get();

        return view('livewire.sites.page-listing', [
            'pages' => $pages,
        ]);
    }
}
