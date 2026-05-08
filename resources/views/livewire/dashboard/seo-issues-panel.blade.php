<div>
    <x-ui.card class="h-full">
        <x-ui.card-header>
            <x-ui.card-title>
                <flux:icon name="magnifying-glass" class="size-4" />
                SEO
            </x-ui.card-title>
            <span class="text-xs text-zinc-500">{{ $totalCount }} issues</span>
        </x-ui.card-header>

        <div>
            @forelse ($issues as $issue)
                <a href="{{ route('seo.meta', [$issue['page']->site_id, $issue['page']->id]) }}" class="issue-item hover:bg-secondary/30 rounded-md -mx-1 px-1 transition-colors">
                    <span @class([
                        'issue-icon',
                        'issue-icon-red' => $issue['severity'] === 'error',
                        'issue-icon-yellow' => $issue['severity'] === 'warning',
                        'issue-icon-blue' => !in_array($issue['severity'], ['error', 'warning']),
                    ])><flux:icon name="exclamation-triangle" /></span>
                    <div class="min-w-0 flex-1">
                        <p class="issue-text">{{ $issue['message'] }}</p>
                        <p class="issue-meta">{{ $issue['site'] }}</p>
                    </div>
                    @if ($issue['severity'] === 'error')
                        <x-ui.badge variant="destructive">Error</x-ui.badge>
                    @elseif ($issue['severity'] === 'warning')
                        <x-ui.badge variant="warning">Warn</x-ui.badge>
                    @else
                        <x-ui.badge variant="info">Info</x-ui.badge>
                    @endif
                </a>
            @empty
                <x-ui.empty icon="check-circle" title="No SEO issues found" />
            @endforelse
        </div>
    </x-ui.card>
</div>
