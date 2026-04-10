<div wire:poll.15s>
    <div class="rounded-xl border border-zinc-800/80 bg-[#1e1e1e] p-5">
        <div class="mb-4 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <flux:icon name="arrow-path" class="size-4 text-zinc-500" />
                <h3 class="text-sm font-semibold text-zinc-100">Recent activity</h3>
            </div>
        </div>

        <div class="space-y-1 max-h-72 overflow-y-auto">
            @forelse ($activities as $activity)
                <div class="flex items-start gap-3 rounded-lg px-3 py-2 transition hover:bg-zinc-800/60">
                    @switch($activity->status)
                        @case('success')
                            <span class="mt-1 size-2 shrink-0 rounded-full bg-emerald-400"></span>
                            @break
                        @case('failed')
                            <span class="mt-1 size-2 shrink-0 rounded-full bg-red-400"></span>
                            @break
                        @default
                            <span class="mt-1 size-2 shrink-0 rounded-full bg-amber-400 animate-pulse"></span>
                    @endswitch

                    <div class="min-w-0 flex-1">
                        <p class="text-sm text-zinc-100">
                            Deployed {{ $activity->site?->name ?? 'Unknown' }}
                            @if ($activity->commit_sha)
                                — <span class="font-mono text-xs text-zinc-400">{{ Str::limit($activity->commit_sha, 7, '') }}</span>
                            @endif
                        </p>
                        <p class="text-xs text-zinc-500">{{ $activity->created_at->diffForHumans() }}</p>
                    </div>
                </div>
            @empty
                <div class="py-8 text-center">
                    <p class="text-sm text-zinc-500">No deploy activity yet</p>
                </div>
            @endforelse
        </div>
    </div>
</div>
