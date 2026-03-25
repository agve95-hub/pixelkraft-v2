<?php

namespace App\Livewire\Dashboard;

use App\Models\Notification;
use App\Models\Site;
use Livewire\Component;

class AlertsPanel extends Component
{
    public function dismiss(string $id): void
    {
        Notification::where('id', $id)->update(['is_read' => true]);
    }

    public function render()
    {
        $alerts = Notification::query()
            ->unread()
            ->with('site')
            ->latest('created_at')
            ->limit(10)
            ->get();

        // SSL expiring soon
        $sslExpiring = Site::query()
            ->where('ssl_status', 'active')
            ->whereNotNull('ssl_expires_at')
            ->where('ssl_expires_at', '<=', now()->addDays(14))
            ->get();

        return view('livewire.dashboard.alerts-panel', [
            'alerts'      => $alerts,
            'sslExpiring' => $sslExpiring,
        ]);
    }
}
