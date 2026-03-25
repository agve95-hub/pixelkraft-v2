<?php

namespace App\Livewire\Dashboard;

use App\Models\DeployLog;
use Livewire\Component;

class ActivityFeed extends Component
{
    public function render()
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
