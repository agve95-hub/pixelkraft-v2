<div wire:poll.15s>
    <flux:card>
        <flux:heading size="sm" class="mb-4">Recent Activity</flux:heading>

        <div class="space-y-1 max-h-96 overflow-y-auto">
            @forelse ($activities as $activity)
                <div class="flex items-start gap-3 rounded-lg px-3 py-2 hover:bg-zinc-50 dark:hover:bg-white/5 transition">
                    @switch($activity->status)
                        @case('success')
                            <flux:icon name="check-circle" variant="solid" class="size-5 text-lime-500 mt-0.5 shrink-0" />
                            @break
                        @case('failed')
                            <flux:icon name="x-circle" variant="solid" class="size-5 text-red-500 mt-0.5 shrink-0" />
                            @break
                        @default
                            <flux:icon name="arrow-path" class="size-5 text-amber-500 mt-0.5 shrink-0 animate-spin" />
                    @endswitch

                    <div class="min-w-0 flex-1">
                        <flux:text size="sm">
                            <span class="font-medium">{{ $activity->site?->name ?? 'Unknown' }}</span>
                            —
                            {{ $activity->commit_message ? Str::limit($activity->commit_message, 60) : ucfirst($activity->status) . ' deploy' }}
                        </flux:text>
                        <div class="flex items-center gap-3 mt-0.5">
                            <flux:text size="xs" class="font-mono">{{ $activity->created_at->diffForHumans() }}</flux:text>
                            @if ($activity->duration_ms)
                                <flux:text size="xs" class="font-mono">{{ $activity->durationFormatted() }}</flux:text>
                            @endif
                            @if ($activity->commit_sha)
                                <flux:text size="xs" class="font-mono">{{ Str::limit($activity->commit_sha, 7, '') }}</flux:text>
                            @endif
                            <flux:badge size="sm" color="zinc" inset="top bottom">{{ $activity->triggered_by }}</flux:badge>
                        </div>
                    </div>
                </div>
            @empty
                <div class="py-8 text-center">
                    <flux:subheading>No deploy activity yet</flux:subheading>
                    <flux:text size="sm" class="mt-1">Trigger your first deploy from a site detail page.</flux:text>
                </div>
            @endforelse
        </div>
    </flux:card>
</div>
