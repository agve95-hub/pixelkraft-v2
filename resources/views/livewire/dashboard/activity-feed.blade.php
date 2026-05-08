<div wire:poll.15s>
    <div class="dash-card">
        <div class="dash-card-head">
            <p class="dash-card-title">
                <flux:icon name="arrow-path" class="size-4" />
                Recent activity
            </p>
        </div>

        <div class="max-h-72 overflow-y-auto">
            @forelse ($activities as $activity)
                <div class="activity-item">
                    @switch($activity->status)
                        @case('success')
                            <span class="activity-dot" style="background:var(--pk-accent)"></span>
                            @break
                        @case('failed')
                            <span class="activity-dot" style="background:var(--red)"></span>
                            @break
                        @default
                            <span class="activity-dot animate-pulse" style="background:var(--amber)"></span>
                    @endswitch

                    <div class="min-w-0 flex-1">
                        <p class="activity-text">
                            Deployed {{ $activity->site?->name ?? 'Unknown' }}
                            @if ($activity->commit_sha)
                                — <span class="tag">{{ Str::limit($activity->commit_sha, 7, '') }}</span>
                            @endif
                        </p>
                        <p class="activity-time">{{ $activity->created_at->diffForHumans() }}</p>
                    </div>
                </div>
            @empty
                <div class="empty">
                    <p>No deploy activity yet</p>
                </div>
            @endforelse
        </div>
    </div>
</div>
