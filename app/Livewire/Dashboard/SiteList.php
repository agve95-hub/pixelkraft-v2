<?php

namespace App\Livewire\Dashboard;

use App\Support\SiteAccess;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class SiteList extends Component
{
    use WithPagination;

    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $sites = SiteAccess::query()
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->withCount('pages')
            ->with('latestDeploy', 'latestUptimeCheck')
            ->orderBy('name')
            ->paginate(25);

        return view('livewire.dashboard.site-list', [
            'sites' => $sites,
        ]);
    }
}
