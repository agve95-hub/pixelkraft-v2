<?php

namespace App\Livewire\Dashboard;

use App\Models\Site;
use Livewire\Component;

class SiteList extends Component
{
    public string $search = '';

    public function render()
    {
        $sites = Site::query()
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->withCount('pages')
            ->with('latestDeploy', 'latestUptimeCheck')
            ->orderBy('name')
            ->get();

        return view('livewire.dashboard.site-list', [
            'sites' => $sites,
        ]);
    }
}
