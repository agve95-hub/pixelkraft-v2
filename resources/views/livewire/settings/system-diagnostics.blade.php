<div class="space-y-6" wire:poll.10s>
    <div class="pk-page-head">
        <div>
            <h1 class="pk-page-title">System diagnostics</h1>
            <p class="pk-page-sub">Queue, worker, and stuck-site visibility for the current pixelkraft runtime.</p>
        </div>
        <flux:button wire:click="$refresh" variant="outline" size="sm" icon="arrow-path">Refresh</flux:button>
    </div>

    @php
        $calloutVariant = $workerHealth['status'] === 'pass' ? 'success' : ($workerHealth['status'] === 'warn' ? 'warning' : 'danger');
        $calloutIcon = $workerHealth['status'] === 'pass' ? 'check-circle' : 'exclamation-triangle';
    @endphp
    <x-ui.alert :variant="$calloutVariant" :icon="$calloutIcon" :title="$workerHealth['label']">
        {{ $workerHealth['message'] }}
    </x-ui.alert>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <x-ui.card>
            <p class="stat-label">Queue Driver</p>
            <div class="mt-2 flex items-center gap-2">
                <x-ui.badge variant="{{ $systemInfo['queue_driver'] === 'redis' ? 'success' : 'destructive' }}">{{ $systemInfo['queue_driver'] }}</x-ui.badge>
                <span class="text-xs text-zinc-500">{{ $systemInfo['queue_connection'] }}</span>
            </div>
            <p class="mt-2 font-mono text-xs text-zinc-500">{{ $systemInfo['app_environment'] }}</p>
        </x-ui.card>

        <x-ui.card>
            <p class="stat-label">Redis</p>
            <div class="mt-2 flex items-center gap-2">
                <x-ui.badge variant="{{ $systemInfo['redis_status']['ok'] ? 'success' : 'destructive' }}">
                    {{ $systemInfo['redis_status']['ok'] ? 'Reachable' : 'Unavailable' }}
                </x-ui.badge>
                <span class="text-xs text-zinc-500">{{ $systemInfo['redis_status']['connection'] }}</span>
            </div>
        </x-ui.card>

        <x-ui.card>
            <p class="stat-label">Pending Jobs</p>
            <p class="stat-val mt-2 font-mono">{{ $summary['pending_jobs'] }}</p>
        </x-ui.card>

        <x-ui.card>
            <p class="stat-label">Stuck Sites</p>
            <p class="stat-val mt-2 font-mono">{{ $summary['stuck_sites'] }}</p>
        </x-ui.card>

        <x-ui.card>
            <p class="stat-label">Failures (24h)</p>
            <p class="stat-val mt-2 font-mono">{{ $summary['failed_jobs'] }}</p>
        </x-ui.card>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <x-ui.card>
            <x-ui.card-header>
                <x-ui.card-title>Health Checks</x-ui.card-title>
            </x-ui.card-header>
            <div class="space-y-2">
                @foreach ($checks as $check)
                    @php
                        $checkVariant = match ($check['status']) { 'pass' => 'success', 'warn' => 'warning', default => 'destructive' };
                    @endphp
                    <div class="flex items-start justify-between gap-3 rounded-lg border border-zinc-800/70 px-4 py-3">
                        <div>
                            <p class="text-sm font-medium">{{ $check['title'] }}</p>
                            <p class="mt-1 text-xs text-zinc-500">{{ $check['message'] }}</p>
                        </div>
                        <x-ui.badge variant="{{ $checkVariant }}">{{ strtoupper($check['status']) }}</x-ui.badge>
                    </div>
                @endforeach
            </div>
        </x-ui.card>

        <x-ui.card>
            <x-ui.card-header>
                <x-ui.card-title>Recommended Actions</x-ui.card-title>
            </x-ui.card-header>
            <div class="space-y-2">
                @foreach ($recommendations as $recommendation)
                    <div class="rounded-lg bg-zinc-950/30 px-4 py-3 text-sm text-zinc-300">{{ $recommendation }}</div>
                @endforeach
            </div>
        </x-ui.card>
    </div>

    <x-ui.card>
        <x-ui.card-header>
            <x-ui.card-title>Queue Breakdown</x-ui.card-title>
        </x-ui.card-header>
        <div class="space-y-2">
            @foreach ($queueStats as $queue)
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-zinc-800/70 px-4 py-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <p class="text-sm font-medium">{{ strtoupper($queue['name']) }}</p>
                            @if (in_array($queue['name'], $systemInfo['configured_queues']))
                                <x-ui.badge variant="success">Horizon</x-ui.badge>
                            @else
                                <x-ui.badge variant="destructive">Missing</x-ui.badge>
                            @endif
                        </div>
                        @if ($queue['error'])
                            <p class="mt-1 text-xs text-red-500">{{ $queue['error'] }}</p>
                        @elseif ($queue['oldest_wait'])
                            <p class="mt-1 text-xs text-zinc-500">Oldest waiting job: {{ $queue['oldest_wait'] }}</p>
                        @else
                            <p class="mt-1 text-xs text-zinc-500">No queue-age signal available for the current driver.</p>
                        @endif
                    </div>
                    <p class="font-mono text-lg font-semibold">{{ $queue['pending'] ?? '—' }}</p>
                </div>
            @endforeach
        </div>
    </x-ui.card>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <x-ui.card>
            <x-ui.card-header>
                <x-ui.card-title>Recent Failed Jobs</x-ui.card-title>
            </x-ui.card-header>
            <div class="space-y-2">
                @forelse ($recentFailures as $failure)
                    <div class="flex items-start justify-between gap-3 rounded-lg border border-zinc-800/70 px-4 py-3">
                        <div class="min-w-0">
                            <p class="text-sm font-medium">{{ $failure['name'] }}</p>
                            <p class="mt-1 font-mono text-xs text-zinc-500">{{ $failure['queue'] }} &middot; {{ $failure['failed_at']->diffForHumans() }}</p>
                            <p class="mt-2 text-sm text-zinc-400">{{ $failure['summary'] }}</p>
                        </div>
                        <x-ui.badge variant="destructive">Failed</x-ui.badge>
                    </div>
                @empty
                    <x-ui.empty icon="check-circle" title="No recent failed jobs" />
                @endforelse
            </div>
        </x-ui.card>

        <x-ui.card>
            <x-ui.card-header>
                <x-ui.card-title>Stuck Sites</x-ui.card-title>
            </x-ui.card-header>
            <div class="space-y-2">
                @foreach (['setup' => 'Initial Setup', 'deploy' => 'Deploy Pipeline'] as $key => $label)
                    <div class="rounded-lg bg-zinc-950/30 px-4 py-3">
                        <div class="mb-3 flex items-center justify-between gap-3">
                            <p class="text-sm font-medium">{{ $label }}</p>
                            <x-ui.badge variant="{{ empty($stuckSites[$key]) ? 'success' : 'destructive' }}">{{ count($stuckSites[$key]) }}</x-ui.badge>
                        </div>
                        <div class="space-y-2">
                            @forelse ($stuckSites[$key] as $site)
                                <div class="flex items-start justify-between gap-3 rounded-lg border border-zinc-800/70 px-3 py-3">
                                    <div>
                                        <p class="text-sm font-medium">{{ $site['name'] }}</p>
                                        <p class="mt-1 font-mono text-xs text-zinc-500">{{ $site['project_type'] }} &middot; {{ $site['age'] }}</p>
                                        <p class="mt-2 text-sm text-zinc-400">{{ $site['reason'] }}</p>
                                    </div>
                                    <x-ui.button href="{{ route('sites.show', $site['id']) }}" size="xs" variant="outline">Open</x-ui.button>
                                </div>
                            @empty
                                <p class="text-sm text-zinc-500">No stuck sites in this category.</p>
                            @endforelse
                        </div>
                    </div>
                @endforeach
            </div>
        </x-ui.card>
    </div>
</div>
