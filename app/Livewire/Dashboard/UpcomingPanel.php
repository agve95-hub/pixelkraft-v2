<?php

namespace App\Livewire\Dashboard;

use App\Models\Site;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class UpcomingPanel extends Component
{
    public function render(): View
    {
        $upcoming = collect();

        $sslPending = Site::where('ssl_status', 'pending')->get();
        foreach ($sslPending as $site) {
            $upcoming->push([
                'icon' => 'shield-exclamation',
                'color' => 'red',
                'title' => 'Resolve SSL provisioning',
                'subtitle' => $site->name,
                'date' => now(),
                'overdue' => true,
            ]);
        }

        $noDomain = Site::whereNull('domain')->orWhere('domain', '')->get();
        foreach ($noDomain as $site) {
            $upcoming->push([
                'icon' => 'globe-alt',
                'color' => 'blue',
                'title' => 'Configure custom domain',
                'subtitle' => $site->name,
                'date' => now()->addDay(),
                'overdue' => false,
            ]);
        }

        $upcoming->push([
            'icon' => 'document-text',
            'color' => 'zinc',
            'title' => 'Send monthly report',
            'subtitle' => 'All sites',
            'date' => now()->endOfMonth(),
            'overdue' => false,
        ]);

        $noAnalytics = Site::whereNull('ga_property_id')->orWhere('ga_property_id', '')->limit(3)->get();
        foreach ($noAnalytics as $site) {
            $upcoming->push([
                'icon' => 'chart-bar',
                'color' => 'zinc',
                'title' => 'Add analytics tracking',
                'subtitle' => $site->name,
                'date' => now()->addDays(7),
                'overdue' => false,
            ]);
        }

        return view('livewire.dashboard.upcoming-panel', [
            'upcoming' => $upcoming->take(5),
            'totalCount' => $upcoming->count(),
        ]);
    }
}
