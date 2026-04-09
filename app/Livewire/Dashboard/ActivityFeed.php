<?php

namespace App\Livewire\Dashboard;

use App\Models\DeployLog;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class ActivityFeed extends Component
{
    public function render(): View
    {
        $activities = DeployLog::query()
            ->with('site')
            ->latest('created_at')
            ->limit(15)
            ->get();

        return view('livewire.dashboard.activity-feed', [
            'activities' => $activities,
        ]);
    }
}
