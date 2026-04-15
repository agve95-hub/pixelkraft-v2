<?php

namespace App\Livewire\Sites;

use App\Models\Page;
use App\Support\SiteAccess;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class PageListing extends Component
{
    use WithPagination;
    public string $siteId;

    public string $search = '';

    public string $sortBy = 'url_path';

    public string $sortDir = 'asc';

    /** Allowlist of columns the user is permitted to sort by. */
    private const SORTABLE_COLUMNS = ['url_path', 'title', 'seo_score', 'updated_at'];

    public function sort(string $column): void
    {
        // Validate column against allowlist to prevent SQL injection via
        // orderBy() — the column name is not parameterized by Eloquent.
        if (! in_array($column, self::SORTABLE_COLUMNS, true)) {
            return;
        }

        if ($this->sortBy === $column) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDir = 'asc';
        }

        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $site = SiteAccess::findOrFail($this->siteId);

        // Clamp the search term and re-validate sortBy/sortDir on render so
        // a manipulated Livewire snapshot cannot inject an arbitrary column.
        $search = mb_substr($this->search, 0, 255);
        $sortBy = in_array($this->sortBy, self::SORTABLE_COLUMNS, true) ? $this->sortBy : 'url_path';
        $sortDir = $this->sortDir === 'desc' ? 'desc' : 'asc';

        $pages = Page::query()
            ->where('site_id', $site->id)
            ->when($search, function ($query) use ($search): void {
                $query->where(function ($nested) use ($search): void {
                    $nested->where('title', 'like', '%'.$search.'%')
                        ->orWhere('url_path', 'like', '%'.$search.'%');
                });
            })
            ->orderBy($sortBy, $sortDir)
            ->paginate(50);

        return view('livewire.sites.page-listing', [
            'pages' => $pages,
        ]);
    }
}
