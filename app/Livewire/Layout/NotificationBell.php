<?php

namespace App\Livewire\Layout;

use App\Models\Notification;
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
        $this->unreadCount = Notification::unread()->count();
    }

    public function markAllRead(): void
    {
        Notification::unread()->update(['is_read' => true]);
        $this->unreadCount = 0;
    }

    public function render(): View
    {
        $notifications = Notification::query()
            ->latest('created_at')
            ->limit(10)
            ->get();

        return view('livewire.layout.notification-bell', [
            'notifications' => $notifications,
        ]);
    }
}
