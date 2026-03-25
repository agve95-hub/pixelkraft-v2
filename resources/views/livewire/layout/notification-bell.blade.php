<div x-data="{ open: false }" class="relative" wire:poll.60s="refreshCount">
    <button
        x-on:click="open = !open"
        class="relative rounded-lg p-1.5 text-zinc-400 hover:bg-zinc-800 hover:text-zinc-200 transition"
    >
        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
        </svg>

        @if ($unreadCount > 0)
            <span class="absolute -top-0.5 -right-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-[10px] font-bold text-white">
                {{ $unreadCount > 9 ? '9+' : $unreadCount }}
            </span>
        @endif
    </button>

    {{-- Dropdown --}}
    <div
        x-show="open"
        x-on:click.outside="open = false"
        x-transition
        class="absolute right-0 mt-2 w-80 rounded-xl border border-zinc-800 bg-zinc-900 shadow-xl z-50"
        x-cloak
    >
        <div class="flex items-center justify-between border-b border-zinc-800 px-4 py-3">
            <span class="text-sm font-semibold text-zinc-200">Notifications</span>
            @if ($unreadCount > 0)
                <button
                    wire:click="markAllRead"
                    class="text-xs text-violet-400 hover:text-violet-300"
                >
                    Mark all read
                </button>
            @endif
        </div>

        <div class="max-h-80 overflow-y-auto">
            @forelse ($notifications as $notification)
                <div @class([
                    'flex gap-3 px-4 py-3 border-b border-zinc-800/50 last:border-0',
                    'bg-violet-500/5' => !$notification->is_read,
                ])>
                    {{-- Icon by type --}}
                    <div class="mt-0.5 flex-shrink-0">
                        @switch($notification->type)
                            @case('deploy_failed')
                                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-red-500/10 text-red-400">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                                </span>
                                @break
                            @case('ssl_expiring')
                                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-amber-500/10 text-amber-400">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" /></svg>
                                </span>
                                @break
                            @case('form_received')
                                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-blue-500/10 text-blue-400">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0-8.953 5.527a2.016 2.016 0 0 1-2.094 0L2.25 6.75" /></svg>
                                </span>
                                @break
                            @default
                                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-400">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                                </span>
                        @endswitch
                    </div>

                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-zinc-200 truncate">{{ $notification->title }}</p>
                        @if ($notification->body)
                            <p class="text-xs text-zinc-500 mt-0.5 line-clamp-2">{{ $notification->body }}</p>
                        @endif
                        <p class="text-xs text-zinc-600 mt-1 mono">{{ $notification->created_at->diffForHumans() }}</p>
                    </div>
                </div>
            @empty
                <div class="px-4 py-8 text-center text-sm text-zinc-500">
                    No notifications yet
                </div>
            @endforelse
        </div>
    </div>
</div>
