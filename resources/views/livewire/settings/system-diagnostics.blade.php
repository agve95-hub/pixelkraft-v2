<div class="space-y-6" wire:poll.10s>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <flux:heading size="lg">System Diagnostics</flux:heading>
            <flux:text class="mt-1">Queue, worker, and stuck-site visibility for the current pixelkraft runtime.</flux:text>
        </div>

        <flux:button wire:click="$refresh" variant="subtle" size="sm" icon="arrow-path">Refresh</flux:button>
    </div>

    <flux:callout
        :variant="$workerHealth['status'] === 'pass' ? 'success' : ($workerHealth['status'] === 'warn' ? 'warning' : 'danger')"
        :icon="$workerHealth['status'] === 'pass' ? 'check-circle' : 'exclamation-triangle'"
    >
        <strong>{{ $workerHealth['label'] }}</strong>
        <div class="mt-1">{{ $workerHealth['message'] }}</div>
    </flux:callout>

    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-4">
        <flux:card>
            <flux:subheading size="sm">Queue Driver</flux:subheading>
            <div class="mt-2 flex items-center gap-2">
                <flux:badge :color="$systemInfo['queue_driver'] === 'redis' ? 'lime' : 'red'">{{ $systemInfo['queue_driver'] }}</flux:badge>
                <flux:text size="sm">{{ $systemInfo['queue_connection'] }}</flux:text>
            </div>
            <flux:text size="xs" class="mt-2 font-mono">{{ $systemInfo['app_environment'] }}</flux:text>
        </flux:card>

        <flux:card>
            <flux:subheading size="sm">Redis</flux:subheading>
            <div class="mt-2 flex items-center gap-2">
                <flux:badge :color="$systemInfo['redis_status']['ok'] ? 'lime' : 'red'">
                    {{ $systemInfo['redis_status']['ok'] ? 'Reachable' : 'Unavailable' }}
                </flux:badge>
                <flux:text size="sm">{{ $systemInfo['redis_status']['connection'] }}</flux:text>
            </div>
        </flux:card>

        <flux:card>
            <flux:subheading size="sm">Pending Jobs</flux:subheading>
            <flux:heading size="xl" class="mt-2 font-mono">{{ $summary['pending_jobs'] }}</flux:heading>
        </flux:card>

        <flux:card>
            <flux:subheading size="sm">Stuck Sites</flux:subheading>
            <flux:heading size="xl" class="mt-2 font-mono">{{ $summary['stuck_sites'] }}</flux:heading>
        </flux:card>

        <flux:card>
            <flux:subheading size="sm">Failures (24h)</flux:subheading>
            <flux:heading size="xl" class="mt-2 font-mono">{{ $summary['failed_jobs'] }}</flux:heading>
        </flux:card>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        <flux:card>
            <flux:heading size="sm" class="mb-4">Health Checks</flux:heading>

            <div class="space-y-3">
                @foreach ($checks as $check)
                    <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 px-4 py-3">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <flux:text size="sm" class="font-medium">{{ $check['title'] }}</flux:text>
                                <flux:text size="sm" class="mt-1">{{ $check['message'] }}</flux:text>
                            </div>

                            <flux:badge :color="$check['status'] === 'pass' ? 'lime' : ($check['status'] === 'warn' ? 'yellow' : 'red')">
                                {{ strtoupper($check['status']) }}
                            </flux:badge>
                        </div>
                    </div>
                @endforeach
            </div>
        </flux:card>

        <flux:card>
            <flux:heading size="sm" class="mb-4">Recommended Actions</flux:heading>

            <div class="space-y-3">
                @foreach ($recommendations as $recommendation)
                    <div class="rounded-lg bg-zinc-50 dark:bg-white/5 px-4 py-3">
                        <flux:text size="sm">{{ $recommendation }}</flux:text>
                    </div>
                @endforeach
            </div>
        </flux:card>
    </div>

    <flux:card>
        <flux:heading size="sm" class="mb-4">Queue Breakdown</flux:heading>

        <div class="space-y-2">
            @foreach ($queueStats as $queue)
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-zinc-200 dark:border-zinc-700 px-4 py-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <flux:text size="sm" class="font-medium">{{ strtoupper($queue['name']) }}</flux:text>
                            @if (in_array($queue['name'], $systemInfo['configured_queues']))
                                <flux:badge color="lime">Horizon</flux:badge>
                            @else
                                <flux:badge color="red">Missing</flux:badge>
                            @endif
                        </div>

                        @if ($queue['error'])
                            <flux:text size="xs" class="mt-1 text-red-500">{{ $queue['error'] }}</flux:text>
                        @elseif ($queue['oldest_wait'])
                            <flux:text size="xs" class="mt-1">Oldest waiting job: {{ $queue['oldest_wait'] }}</flux:text>
                        @else
                            <flux:text size="xs" class="mt-1">No queue-age signal available for the current driver.</flux:text>
                        @endif
                    </div>

                    <flux:heading size="lg" class="font-mono">
                        {{ $queue['pending'] ?? '—' }}
                    </flux:heading>
                </div>
            @endforeach
        </div>
    </flux:card>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        <flux:card>
            <flux:heading size="sm" class="mb-4">Recent Failed Jobs</flux:heading>

            <div class="space-y-2">
                @forelse ($recentFailures as $failure)
                    <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 px-4 py-3">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <flux:text size="sm" class="font-medium">{{ $failure['name'] }}</flux:text>
                                <flux:text size="xs" class="mt-1 font-mono">{{ $failure['queue'] }} · {{ $failure['failed_at']->diffForHumans() }}</flux:text>
                                <flux:text size="sm" class="mt-2">{{ $failure['summary'] }}</flux:text>
                            </div>
                            <flux:badge color="red">Failed</flux:badge>
                        </div>
                    </div>
                @empty
                    <div class="py-8 text-center">
                        <flux:icon name="check-circle" variant="outline" class="size-8 text-lime-500 mx-auto mb-2" />
                        <flux:subheading>No recent failed jobs</flux:subheading>
                    </div>
                @endforelse
            </div>
        </flux:card>

        <flux:card>
            <flux:heading size="sm" class="mb-4">Stuck Sites</flux:heading>

            <div class="space-y-2">
                @foreach (['setup' => 'Initial Setup', 'deploy' => 'Deploy Pipeline'] as $key => $label)
                    <div class="rounded-lg bg-zinc-50 dark:bg-white/5 px-4 py-3">
                        <div class="flex items-center justify-between gap-3">
                            <flux:text size="sm" class="font-medium">{{ $label }}</flux:text>
                            <flux:badge :color="empty($stuckSites[$key]) ? 'lime' : 'red'">{{ count($stuckSites[$key]) }}</flux:badge>
                        </div>

                        <div class="mt-3 space-y-2">
                            @forelse ($stuckSites[$key] as $site)
                                <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 px-3 py-3">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <flux:text size="sm" class="font-medium">{{ $site['name'] }}</flux:text>
                                            <flux:text size="xs" class="mt-1 font-mono">{{ $site['project_type'] }} · {{ $site['age'] }}</flux:text>
                                            <flux:text size="sm" class="mt-2">{{ $site['reason'] }}</flux:text>
                                        </div>

                                        <flux:button href="{{ route('sites.show', $site['id']) }}" size="xs" variant="subtle">
                                            Open
                                        </flux:button>
                                    </div>
                                </div>
                            @empty
                                <flux:text size="sm" class="text-zinc-500">No stuck sites in this category.</flux:text>
                            @endforelse
                        </div>
                    </div>
                @endforeach
            </div>
        </flux:card>
    </div>
</div>
