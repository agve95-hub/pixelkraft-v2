<?php

namespace App\Livewire\Dashboard;

use App\Models\Notification;
use App\Support\SiteAccess;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class AlertsPanel extends Component
{
    public function dismiss(string $id): void
    {
        Notification::query()
            ->whereKey($id)
            ->whereHas('site', fn ($query) => $query->visibleTo(auth()->user()))
            ->update(['is_read' => true]);
    }

    public function render(): View
    {
        $alerts = Notification::query()
            ->unread()
            ->whereHas('site', fn ($query) => $query->visibleTo(auth()->user()))
            ->with('site')
            ->latest('created_at')
            ->limit(10)
            ->get();

        // SSL expiring soon
        $sslExpiring = SiteAccess::query()
            ->where('ssl_status', 'active')
            ->whereNotNull('ssl_expires_at')
            ->where('ssl_expires_at', '<=', now()->addDays(14))
            ->get();

        return view('livewire.dashboard.alerts-panel', [
            'alerts' => $alerts,
            'sslExpiring' => $sslExpiring,
        ]);
    }
}
