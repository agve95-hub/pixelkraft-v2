<div>
    <x-ui.card class="h-full">
        <x-ui.card-header>
            <x-ui.card-title>
                <flux:icon name="calendar" class="size-4" />
                Upcoming
            </x-ui.card-title>
            <span class="text-xs text-zinc-500">{{ $totalCount }} pending</span>
        </x-ui.card-header>

        <div class="space-y-1">
            @forelse ($upcoming as $item)
                @php($href = $item['href'] ?? null)
                @if ($href)
                    <a href="{{ $href }}" class="issue-item hover:bg-secondary/30 rounded-md -mx-1 px-1 transition-colors">
                @else
                    <div class="issue-item">
                @endif
                    <flux:icon :name="$item['icon']" @class([
                        'mt-0.5 size-4 shrink-0',
                        'text-red-500' => $item['color'] === 'red',
                        'text-blue-500' => $item['color'] === 'blue',
                        'text-zinc-400' => $item['color'] === 'zinc',
                    ]) />
                    <div class="min-w-0 flex-1">
                        <div class="flex items-start justify-between gap-2">
                            <p class="issue-text">{{ $item['title'] }}</p>
                            @if ($item['overdue'])
                                <x-ui.badge variant="destructive">Overdue</x-ui.badge>
                            @endif
                        </div>
                        <p class="issue-meta">{{ $item['subtitle'] }} &middot; {{ $item['date']->format('M j') }}</p>
                    </div>
                @if ($href) </a> @else </div> @endif
            @empty
                <x-ui.empty icon="calendar" title="Nothing upcoming" />
            @endforelse
        </div>
    </x-ui.card>
</div>
