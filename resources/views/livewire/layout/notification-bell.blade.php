<div wire:poll.60s="refreshCount">
    <flux:dropdown position="bottom" align="end">
        <flux:button variant="ghost" size="sm" icon="bell" class="relative">
            @if ($unreadCount > 0)
                <flux:badge size="sm" color="red" class="absolute -top-1 -right-1 !px-1.5 !py-0 text-[10px]">{{ $unreadCount > 9 ? '9+' : $unreadCount }}</flux:badge>
            @endif
        </flux:button>

        <flux:menu class="w-80">
            <div class="flex items-center justify-between px-3 py-2">
                <flux:heading size="sm">Notifications</flux:heading>
                @if ($unreadCount > 0)
                    <flux:button wire:click="markAllRead" size="xs" variant="ghost">Mark all read</flux:button>
                @endif
            </div>

            <flux:separator />

            <div class="max-h-80 overflow-y-auto">
                @forelse ($notifications as $notification)
                    <div class="flex gap-3 px-3 py-2.5 {{ !$notification->is_read ? 'bg-zinc-50 dark:bg-white/5' : '' }}">
                        @switch($notification->type)
                            @case('deploy_failed')
                                <flux:icon name="x-circle" variant="solid" class="size-5 text-red-500 shrink-0 mt-0.5" />
                                @break
                            @case('ssl_expiring')
                                <flux:icon name="exclamation-triangle" variant="solid" class="size-5 text-amber-500 shrink-0 mt-0.5" />
                                @break
                            @case('form_received')
                                <flux:icon name="envelope" variant="solid" class="size-5 text-blue-500 shrink-0 mt-0.5" />
                                @break
                            @default
                                <flux:icon name="check-circle" variant="solid" class="size-5 text-lime-500 shrink-0 mt-0.5" />
                        @endswitch

                        <div class="min-w-0 flex-1">
                            <flux:text size="sm" class="font-medium truncate">{{ $notification->title }}</flux:text>
                            @if ($notification->body)
                                <flux:text size="xs" class="line-clamp-2 mt-0.5">{{ $notification->body }}</flux:text>
                            @endif
                            <flux:text size="xs" class="font-mono mt-1">{{ $notification->created_at->diffForHumans() }}</flux:text>
                        </div>
                    </div>
                @empty
                    <div class="px-3 py-8 text-center">
                        <flux:subheading>No notifications yet</flux:subheading>
                    </div>
                @endforelse
            </div>
        </flux:menu>
    </flux:dropdown>
</div>
