<div>
    <div class="card">
        <h3 class="text-sm font-semibold text-zinc-200 mb-4">Alerts</h3>

        <div class="space-y-2 max-h-96 overflow-y-auto">
            {{-- SSL Expiry Warnings --}}
            @foreach ($sslExpiring as $site)
                <div class="flex items-start gap-3 rounded-lg border border-amber-500/20 bg-amber-500/5 px-3 py-2.5">
                    <span class="mt-0.5 flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full bg-amber-500/10 text-amber-400">
                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" /></svg>
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm text-amber-300">
                            SSL expiring for <span class="font-medium">{{ $site->domain }}</span>
                        </p>
                        <p class="mono text-xs text-amber-400/60 mt-0.5">
                            Expires {{ $site->ssl_expires_at->diffForHumans() }}
                        </p>
                    </div>
                </div>
            @endforeach

            {{-- Notification Alerts --}}
            @foreach ($alerts as $alert)
                <div @class([
                    'flex items-start gap-3 rounded-lg px-3 py-2.5',
                    'border border-red-500/20 bg-red-500/5' => in_array($alert->type, ['deploy_failed', 'uptime_down']),
                    'border border-zinc-800 bg-zinc-800/30' => !in_array($alert->type, ['deploy_failed', 'uptime_down']),
                ])>
                    @switch($alert->type)
                        @case('deploy_failed')
                            <span class="mt-0.5 flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full bg-red-500/10 text-red-400">
                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126Z" /></svg>
                            </span>
                            @break
                        @case('uptime_down')
                            <span class="mt-0.5 flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full bg-red-500/10 text-red-400">
                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18M10.5 10.677a2 2 0 002.823 2.823M7.757 4.903A9.963 9.963 0 0112 4c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-2.3 3.846" /></svg>
                            </span>
                            @break
                        @case('lighthouse_drop')
                            <span class="mt-0.5 flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full bg-amber-500/10 text-amber-400">
                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6 9 12.75l4.286-4.286a11.948 11.948 0 014.306 6.43l.776 2.898" /></svg>
                            </span>
                            @break
                        @default
                            <span class="mt-0.5 flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full bg-blue-500/10 text-blue-400">
                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" /></svg>
                            </span>
                    @endswitch

                    <div class="min-w-0 flex-1">
                        <p class="text-sm text-zinc-200">{{ $alert->title }}</p>
                        @if ($alert->body)
                            <p class="text-xs text-zinc-500 mt-0.5">{{ Str::limit($alert->body, 80) }}</p>
                        @endif
                        <p class="mono text-xs text-zinc-600 mt-0.5">{{ $alert->created_at->diffForHumans() }}</p>
                    </div>

                    <button
                        wire:click="dismiss('{{ $alert->id }}')"
                        class="flex-shrink-0 text-zinc-600 hover:text-zinc-400 transition"
                        title="Dismiss"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                    </button>
                </div>
            @endforeach

            @if ($alerts->isEmpty() && $sslExpiring->isEmpty())
                <div class="py-8 text-center text-sm text-zinc-500">
                    <svg class="mx-auto h-8 w-8 mb-2 text-emerald-500/40" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                    All clear — no alerts
                </div>
            @endif
        </div>
    </div>
</div>
