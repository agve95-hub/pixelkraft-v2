<div wire:poll.15s>
    <x-ui.card>
        <x-ui.card-header>
            <x-ui.card-title>
                <flux:icon name="arrow-path" class="size-4" />
                Recent activity
            </x-ui.card-title>
        </x-ui.card-header>

        <div class="max-h-72 overflow-y-auto">
            @forelse ($activities as $activity)
                <div class="activity-item">
                    @switch($activity->status)
                        @case('success')
                            <span class="activity-dot activity-dot-success"></span>
                            @break
                        @case('failed')
                            <span class="activity-dot activity-dot-danger"></span>
                            @break
                        @default
                            <span class="activity-dot activity-dot-warning animate-pulse"></span>
                    @endswitch
                    <div class="min-w-0 flex-1">
                        <p class="activity-text">
                            Deployed {{ $activity->site?->name ?? 'Unknown' }}
                            @if ($activity->commit_sha)
                                &mdash; <span class="tag">{{ Str::limit($activity->commit_sha, 7, '') }}</span>
                            @endif
                        </p>
                        <p class="activity-time">{{ $activity->created_at->diffForHumans() }}</p>
                    </div>
                </div>
            @empty
                <x-ui.empty icon="arrow-path" title="No deploy activity yet" />
            @endforelse
        </div>
    </x-ui.card>
</div>
