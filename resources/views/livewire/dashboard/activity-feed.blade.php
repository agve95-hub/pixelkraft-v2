<div>
    <div class="card">
        <h3 class="text-sm font-semibold text-zinc-200 mb-4">Recent Activity</h3>

        <div class="space-y-1 max-h-96 overflow-y-auto">
            @forelse ($activities as $activity)
                <div class="flex items-start gap-3 rounded-lg px-3 py-2.5 hover:bg-zinc-800/40 transition">
                    {{-- Status icon --}}
                    @switch($activity->status)
                        @case('success')
                            <span class="mt-0.5 flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-400">
                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                            </span>
                            @break
                        @case('failed')
                            <span class="mt-0.5 flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full bg-red-500/10 text-red-400">
                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                            </span>
                            @break
                        @default
                            <span class="mt-0.5 flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full bg-amber-500/10 text-amber-400">
                                <svg class="h-3 w-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                            </span>
                    @endswitch

                    <div class="min-w-0 flex-1">
                        <p class="text-sm text-zinc-300">
                            <span class="font-medium text-zinc-100">{{ $activity->site?->name ?? 'Unknown' }}</span>
                            —
                            @if ($activity->commit_message)
                                {{ Str::limit($activity->commit_message, 60) }}
                            @else
                                {{ ucfirst($activity->status) }} deploy
                            @endif
                        </p>
                        <div class="flex items-center gap-3 mt-0.5">
                            <span class="mono text-xs text-zinc-600">{{ $activity->created_at->diffForHumans() }}</span>
                            @if ($activity->duration_ms)
                                <span class="mono text-xs text-zinc-600">{{ $activity->durationFormatted() }}</span>
                            @endif
                            @if ($activity->commit_sha)
                                <span class="mono text-xs text-zinc-600">{{ Str::limit($activity->commit_sha, 7, '') }}</span>
                            @endif
                            <span @class([
                                'mono text-xs',
                                'text-zinc-600' => $activity->triggered_by === 'manual',
                                'text-violet-500' => $activity->triggered_by === 'save',
                                'text-cyan-500' => $activity->triggered_by === 'webhook',
                            ])>{{ $activity->triggered_by }}</span>
                        </div>
                    </div>
                </div>
            @empty
                <div class="py-8 text-center text-sm text-zinc-500">
                    No deploy activity yet
                </div>
            @endforelse
        </div>
    </div>
</div>
