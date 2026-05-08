<div wire:poll.60s="refreshCount">
    <flux:dropdown position="bottom" align="end">
        <flux:button variant="ghost" size="sm" icon="bell" class="relative">
            @if ($unreadCount > 0)
                <span class="absolute -right-1 -top-1 inline-flex min-w-[1.1rem] items-center justify-center rounded-full bg-red-500 px-1 py-0.5 text-[10px] font-bold text-white">{{ $unreadCount > 9 ? '9+' : $unreadCount }}</span>
            @endif
        </flux:button>

        <flux:menu class="w-80">
            <div class="flex items-center justify-between px-3 py-2">
                <p class="text-sm font-semibold">Notifications</p>
                @if ($unreadCount > 0)
                    <flux:button wire:click="markAllRead" size="xs" variant="ghost">Mark all read</flux:button>
                @endif
            </div>

            <flux:separator />

            <div class="max-h-80 overflow-y-auto">
                @forelse ($notifications as $notification)
                    <div class="flex gap-3 px-3 py-2.5 {{ !$notification->is_read ? 'bg-white/5' : '' }}">
                        @switch($notification->type)
                            @case('deploy_failed')
                                <flux:icon name="x-circle" variant="solid" class="mt-0.5 size-5 shrink-0 text-red-500" />
                                @break
                            @case('ssl_expiring')
                                <flux:icon name="exclamation-triangle" variant="solid" class="mt-0.5 size-5 shrink-0 text-amber-500" />
                                @break
                            @case('form_received')
                                <flux:icon name="envelope" variant="solid" class="mt-0.5 size-5 shrink-0 text-blue-500" />
                                @break
                            @default
                                <flux:icon name="check-circle" variant="solid" class="mt-0.5 size-5 shrink-0 text-emerald-500" />
                        @endswitch

                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium">{{ $notification->title }}</p>
                            @if ($notification->body)
                                <p class="mt-0.5 line-clamp-2 text-xs text-zinc-500">{{ $notification->body }}</p>
                            @endif
                            <p class="mt-1 font-mono text-xs text-zinc-600">{{ $notification->created_at->diffForHumans() }}</p>
                        </div>
                    </div>
                @empty
                    <div class="px-3 py-8 text-center">
                        <p class="pk-page-sub">No notifications yet</p>
                    </div>
                @endforelse
            </div>
        </flux:menu>
    </flux:dropdown>
</div>
