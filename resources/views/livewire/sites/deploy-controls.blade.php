<div class="space-y-6">
    {{-- Deploy Actions --}}
    <div class="card">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-semibold text-zinc-200">Deploy</h3>
            <div class="flex items-center gap-2">
                @if ($site->domain && !$site->nginx_conf_path)
                    <button wire:click="setupDomain" class="btn-secondary text-xs">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582" /></svg>
                        Setup Domain &amp; SSL
                    </button>
                @endif

                <button
                    wire:click="deploy"
                    class="btn-primary text-xs"
                    wire:loading.attr="disabled"
                    wire:target="deploy"
                    @disabled(in_array($site->deploy_status, ['building', 'deploying']))
                >
                    @if (in_array($site->deploy_status, ['building', 'deploying']))
                        <svg class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                        {{ ucfirst($site->deploy_status) }}...
                    @else
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0 3 3m-3-3-3 3M6.75 19.5a4.5 4.5 0 0 1-1.41-8.775 5.25 5.25 0 0 1 10.233-2.33 3 3 0 0 1 3.758 3.848A3.752 3.752 0 0 1 18 19.5H6.75Z" /></svg>
                        Deploy Now
                    @endif
                </button>
            </div>
        </div>

        {{-- Status overview --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <div class="rounded-lg bg-zinc-800/40 px-3 py-2">
                <p class="text-[10px] uppercase tracking-wider text-zinc-600">Status</p>
                <div class="mt-1">
                    @switch($site->deploy_status)
                        @case('live')
                            <span class="badge-green">Live</span>
                            @break
                        @case('building')
                        @case('deploying')
                            <span class="badge-amber">{{ ucfirst($site->deploy_status) }}</span>
                            @break
                        @case('failed')
                            <span class="badge-red">Failed</span>
                            @break
                        @default
                            <span class="badge bg-zinc-500/10 text-zinc-500">Idle</span>
                    @endswitch
                </div>
            </div>
            <div class="rounded-lg bg-zinc-800/40 px-3 py-2">
                <p class="text-[10px] uppercase tracking-wider text-zinc-600">SSL</p>
                <div class="mt-1">
                    @switch($site->ssl_status)
                        @case('active')
                            <span class="badge-green">Active</span>
                            @break
                        @case('expired')
                            <span class="badge-red">Expired</span>
                            @break
                        @case('error')
                            <span class="badge-red">Error</span>
                            @break
                        @default
                            <span class="badge bg-zinc-500/10 text-zinc-500">Pending</span>
                    @endswitch
                </div>
            </div>
            <div class="rounded-lg bg-zinc-800/40 px-3 py-2">
                <p class="text-[10px] uppercase tracking-wider text-zinc-600">Last Deploy</p>
                <p class="text-xs text-zinc-300 mt-1">{{ $site->last_deployed_at?->diffForHumans() ?? 'Never' }}</p>
            </div>
            <div class="rounded-lg bg-zinc-800/40 px-3 py-2">
                <p class="text-[10px] uppercase tracking-wider text-zinc-600">Domain</p>
                <p class="text-xs text-zinc-300 mt-1 mono truncate">{{ $site->domain ?? 'Not set' }}</p>
            </div>
        </div>
    </div>

    {{-- Deploy Logs --}}
    <div class="card">
        <h3 class="text-sm font-semibold text-zinc-200 mb-4">Deploy History</h3>

        <div class="space-y-1">
            @forelse ($deployLogs as $log)
                <div class="flex items-center gap-3 rounded-lg px-3 py-2 hover:bg-zinc-800/30 transition">
                    {{-- Status icon --}}
                    @switch($log->status)
                        @case('success')
                            <span class="flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-400">
                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                            </span>
                            @break
                        @case('failed')
                            <span class="flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full bg-red-500/10 text-red-400">
                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                            </span>
                            @break
                        @default
                            <span class="flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full bg-amber-500/10 text-amber-400">
                                <svg class="h-3 w-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                            </span>
                    @endswitch

                    {{-- Info --}}
                    <div class="flex-1 min-w-0">
                        <p class="text-xs text-zinc-300">
                            {{ $log->commit_message ?? ucfirst($log->status) . ' deploy' }}
                        </p>
                        <div class="flex items-center gap-3 mt-0.5">
                            <span class="mono text-[10px] text-zinc-600">{{ $log->created_at->diffForHumans() }}</span>
                            @if ($log->duration_ms)
                                <span class="mono text-[10px] text-zinc-600">{{ $log->durationFormatted() }}</span>
                            @endif
                            @if ($log->commit_sha)
                                <span class="mono text-[10px] text-zinc-600">{{ Str::limit($log->commit_sha, 7, '') }}</span>
                            @endif
                            <span class="mono text-[10px] text-zinc-600">{{ $log->triggered_by }}</span>
                        </div>
                    </div>

                    {{-- Actions --}}
                    <div class="flex items-center gap-1 flex-shrink-0">
                        <button
                            wire:click="viewLog('{{ $log->id }}')"
                            class="btn-ghost text-[10px] !px-2 !py-0.5"
                        >
                            Log
                        </button>

                        @if ($log->isSuccess() && $log->snapshot_tag)
                            <button
                                wire:click="rollback('{{ $log->id }}')"
                                wire:confirm="Rollback to this deploy? The site will revert to this version."
                                class="text-[10px] text-amber-400 hover:text-amber-300 px-2 py-0.5"
                            >
                                Rollback
                            </button>
                        @endif
                    </div>
                </div>
            @empty
                <div class="py-8 text-center text-sm text-zinc-500">
                    No deploys yet. Click "Deploy Now" to start.
                </div>
            @endforelse
        </div>
    </div>

    {{-- Log Viewer Modal --}}
    @if ($viewingLog)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60" x-on:keydown.escape.window="$wire.closeLog()">
            <div class="w-full max-w-3xl max-h-[80vh] rounded-xl border border-zinc-800 bg-zinc-900 shadow-2xl flex flex-col" x-on:click.outside="$wire.closeLog()">
                <div class="flex items-center justify-between px-4 py-3 border-b border-zinc-800">
                    <div>
                        <h3 class="text-sm font-semibold text-zinc-200">Deploy Log</h3>
                        <p class="mono text-[10px] text-zinc-600">
                            {{ $viewingLog->created_at->format('M j, Y H:i:s') }}
                            · {{ $viewingLog->durationFormatted() }}
                            · {{ $viewingLog->triggered_by }}
                            @if ($viewingLog->commit_sha)
                                · {{ Str::limit($viewingLog->commit_sha, 7, '') }}
                            @endif
                        </p>
                    </div>
                    <button wire:click="closeLog" class="text-zinc-600 hover:text-zinc-400">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                <div class="flex-1 overflow-y-auto p-4">
                    <pre class="mono text-xs text-zinc-400 whitespace-pre-wrap leading-relaxed">{{ $viewingLog->output_log ?? 'No output recorded.' }}</pre>
                </div>
            </div>
        </div>
    @endif
</div>
