<?php

namespace App\Livewire\Layout;

use App\Models\Notification;
use App\Support\SiteAccess;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class NotificationBell extends Component
{
    public int $unreadCount = 0;

    public function mount(): void
    {
        $this->refreshCount();
    }

    public function refreshCount(): void
    {
        $this->unreadCount = $this->visibleNotificationQuery()
            ->unread()
            ->count();
    }

    public function markAllRead(): void
    {
        $this->visibleNotificationQuery()
            ->unread()
            ->update(['is_read' => true]);
        $this->unreadCount = 0;
    }

    public function render(): View
    {
        $notifications = $this->visibleNotificationQuery()
            ->latest('created_at')
            ->limit(10)
            ->get();

        return view('livewire.layout.notification-bell', [
            'notifications' => $notifications,
        ]);
    }

    private function visibleNotificationQuery()
    {
        $query = Notification::query();
        $user = auth()->user();

        if ($user?->isAdmin()) {
            return $query;
        }

        $visibleSiteIds = SiteAccess::query()->pluck('id');

        return $query->whereIn('site_id', $visibleSiteIds);
    }
}
