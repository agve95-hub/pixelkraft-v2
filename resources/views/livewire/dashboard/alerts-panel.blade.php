<div wire:poll.60s>
    <div class="rounded-xl border border-zinc-800/80 bg-[#1e1e1e] p-5">
        <div class="mb-4 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <flux:icon name="exclamation-triangle" class="size-4 text-amber-400" />
                <h3 class="text-sm font-semibold text-zinc-100">Action needed</h3>
            </div>
            @php
                $alertTotal = $alerts->count() + $sslExpiring->count();
            @endphp
            @if ($alertTotal > 0)
                <span class="text-xs text-zinc-500">{{ $alertTotal }} {{ Str::plural('item', $alertTotal) }}</span>
            @endif
        </div>

        <div class="space-y-1 max-h-72 overflow-y-auto">
            @foreach ($sslExpiring as $site)
                <div class="flex items-start gap-3 rounded-lg px-3 py-2 hover:bg-zinc-800/70 transition">
                    <span class="mt-1 flex size-5 shrink-0 items-center justify-center rounded-full bg-amber-500/15">
                        <flux:icon name="shield-exclamation" class="size-3.5 text-amber-400" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-zinc-100">SSL pending on {{ $site->name }}</p>
                        <p class="text-xs text-zinc-500">Certificate not yet provisioned</p>
                    </div>
                </div>
            @endforeach

            @foreach ($alerts as $alert)
                <div class="flex items-start gap-3 rounded-lg px-3 py-2 hover:bg-zinc-800/70 transition">
                    <span @class([
                        'mt-1 flex size-5 shrink-0 items-center justify-center rounded-full',
                        'bg-red-500/15' => in_array($alert->type, ['deploy_failed', 'uptime_down']),
                        'bg-amber-500/15' => $alert->type === 'ssl_expiring',
                        'bg-blue-500/15' => !in_array($alert->type, ['deploy_failed', 'uptime_down', 'ssl_expiring']),
                    ])>
                        @switch($alert->type)
                            @case('deploy_failed')
                                <flux:icon name="x-circle" class="size-3.5 text-red-400" />
                                @break
                            @case('uptime_down')
                                <flux:icon name="signal-slash" class="size-3.5 text-red-400" />
                                @break
                            @default
                                <flux:icon name="information-circle" class="size-3.5 text-blue-400" />
                        @endswitch
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-zinc-100">{{ $alert->title }}</p>
                        <p class="text-xs text-zinc-500">
                            {{ $alert->site?->name ?? '' }}
                            @if ($alert->body) &middot; {{ Str::limit($alert->body, 50) }} @endif
                            &middot; {{ $alert->created_at->diffForHumans() }}
                        </p>
                    </div>
                    <flux:button wire:click="dismiss('{{ $alert->id }}')" size="xs" variant="ghost" icon="x-mark" class="shrink-0 !text-zinc-500 hover:!text-zinc-200" />
                </div>
            @endforeach

            @if ($alerts->isEmpty() && $sslExpiring->isEmpty())
                <div class="py-8 text-center">
                    <flux:icon name="check-circle" variant="outline" class="size-8 text-lime-400 mx-auto mb-2" />
                    <p class="text-sm text-zinc-500">All clear — no action needed</p>
                </div>
            @endif
        </div>
    </div>
</div>
