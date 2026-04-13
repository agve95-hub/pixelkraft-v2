<?php

namespace App\Livewire\Dashboard;

use App\Models\DeployLog;
use App\Support\SiteAccess;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class ActivityFeed extends Component
{
    public function render(): View
    {
        $visibleSiteIds = SiteAccess::query()->pluck('id');

        $activities = DeployLog::query()
            ->with('site')
            ->when(
                $visibleSiteIds->isNotEmpty(),
                fn ($q) => $q->whereIn('site_id', $visibleSiteIds),
                fn ($q) => $q->whereRaw('1 = 0'),
            )
            ->latest('created_at')
            ->limit(15)
            ->get();

        return view('livewire.dashboard.activity-feed', [
            'activities' => $activities,
        ]);
    }
}
