<div>
    <div class="h-full rounded-xl border border-zinc-800/80 bg-[#1e1e1e] p-5">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2">
                <flux:icon name="calendar" class="size-4 text-zinc-500" />
                <h3 class="text-sm font-semibold text-zinc-100">Upcoming</h3>
            </div>
            <span class="text-xs text-zinc-500">{{ $totalCount }} pending</span>
        </div>

        <div class="space-y-1.5">
            @forelse ($upcoming as $item)
                <div class="flex items-start gap-3 rounded-lg border border-transparent px-3 py-2.5 transition hover:border-zinc-700/70 hover:bg-zinc-950/50">
                    <flux:icon :name="$item['icon']" @class([
                        'mt-0.5 size-4 shrink-0',
                        'text-red-500' => $item['color'] === 'red',
                        'text-blue-500' => $item['color'] === 'blue',
                        'text-zinc-400' => $item['color'] === 'zinc',
                    ]) />
                    <div class="min-w-0 flex-1">
                        <div class="flex items-start justify-between gap-2">
                            <p class="text-sm text-zinc-100">{{ $item['title'] }}</p>
                            @if ($item['overdue'])
                                <span class="inline-flex shrink-0 rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide bg-red-500/15 text-red-400">Overdue</span>
                            @endif
                        </div>
                        <p class="mt-0.5 text-xs text-zinc-500">
                            {{ $item['subtitle'] }}
                            &middot;
                            {{ $item['date']->format('M j') }}
                        </p>
                    </div>
                </div>
            @empty
                <div class="py-6 text-center">
                    <p class="text-sm text-zinc-500">Nothing upcoming</p>
                </div>
            @endforelse
        </div>
    </div>
</div>
