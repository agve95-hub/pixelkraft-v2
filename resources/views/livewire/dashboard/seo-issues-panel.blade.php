<div>
    <div class="dash-card h-full">
        <div class="dash-card-head">
            <p class="dash-card-title">
                <flux:icon name="magnifying-glass" class="size-4" />
                SEO
            </p>
            <span class="text-xs text-zinc-500">{{ $totalCount }} issues</span>
        </div>

        <div>
            @forelse ($issues as $issue)
                <a href="{{ route('seo.meta', [$issue['page']->site_id, $issue['page']->id]) }}" class="issue-item hover:bg-secondary/30 transition-colors rounded-md -mx-1 px-1">
                    <span @class([
                        'issue-icon',
                        'issue-icon-red' => $issue['severity'] === 'error',
                        'issue-icon-yellow' => $issue['severity'] === 'warning',
                        'issue-icon-blue' => !in_array($issue['severity'], ['error', 'warning']),
                    ])>
                        <flux:icon name="exclamation-triangle" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="issue-text hover:text-emerald-400 transition-colors">{{ $issue['message'] }}</p>
                        <p class="issue-meta">{{ $issue['site'] }}</p>
                    </div>
                    @if ($issue['severity'] === 'error')
                        <span class="pill pill-red pill-no-dot">Error</span>
                    @elseif ($issue['severity'] === 'warning')
                        <span class="pill pill-yellow pill-no-dot">Warn</span>
                    @else
                        <span class="pill pill-blue pill-no-dot">Info</span>
                    @endif
                </a>
            @empty
                <div class="empty">
                    <div class="empty-icon"><flux:icon name="check-circle" variant="outline" /></div>
                    <p>No SEO issues found</p>
                </div>
            @endforelse
        </div>
    </div>
</div>
