<div>
    <div class="rounded-2xl border border-zinc-800/90 bg-zinc-900/85 p-5 h-full">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2">
                <flux:icon name="calendar" class="size-4 text-zinc-500" />
                <h3 class="text-sm font-semibold text-zinc-100">Upcoming</h3>
            </div>
            <span class="text-xs text-zinc-500">{{ $totalCount }} pending</span>
        </div>

        <div class="space-y-1.5">
            @forelse ($upcoming as $item)
                <div class="flex items-start gap-3 rounded-lg border border-transparent px-3 py-2.5 hover:border-zinc-700/70 hover:bg-zinc-950/70 transition">
                    <flux:icon :name="$item['icon']" @class([
                        'size-4 mt-0.5 shrink-0',
                        'text-red-500' => $item['color'] === 'red',
                        'text-blue-500' => $item['color'] === 'blue',
                        'text-zinc-400' => $item['color'] === 'zinc',
                    ]) />
                    <div class="min-w-0 flex-1">
                        <p class="text-sm text-zinc-100">{{ $item['title'] }}</p>
                        <p class="text-xs text-zinc-500">
                            {{ $item['subtitle'] }}
                            &middot;
                            {{ $item['date']->format('M j') }}
                            @if ($item['overdue'])
                                <span class="ml-1 inline-flex rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase bg-red-500/10 text-red-500">Overdue</span>
                            @endif
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
