<div>
    <div class="dash-card h-full">
        <div class="dash-card-head">
            <p class="dash-card-title">
                <flux:icon name="calendar" class="size-4" />
                Upcoming
            </p>
            <span class="text-xs text-zinc-500">{{ $totalCount }} pending</span>
        </div>

        <div class="space-y-1.5">
            @forelse ($upcoming as $item)
                @php($href = $item['href'] ?? null)
                @if ($href)
                    <a href="{{ $href }}" class="flex items-start gap-3 rounded-lg border border-transparent px-3 py-2.5 transition hover:border-zinc-700/70 hover:bg-zinc-950/50">
                @else
                    <div class="flex items-start gap-3 rounded-lg border border-transparent px-3 py-2.5 transition hover:border-zinc-700/70 hover:bg-zinc-950/50">
                @endif
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
                @if ($href)
                    </a>
                @else
                    </div>
                @endif
            @empty
                <div class="py-6 text-center">
                    <p class="text-sm text-zinc-500">Nothing upcoming</p>
                </div>
            @endforelse
        </div>
    </div>
</div>
