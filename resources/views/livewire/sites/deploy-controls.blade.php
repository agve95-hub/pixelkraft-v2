<div class="space-y-6" wire:poll.5s>
    <flux:card>
        <div class="flex items-center justify-between mb-4">
            <flux:heading size="sm">Deploy</flux:heading>
            <div class="flex items-center gap-2">
                @if ($site->domain && !$site->nginx_conf_path)
                    <flux:button wire:click="setupDomain" variant="subtle" size="sm" icon="globe-alt">Setup Domain & SSL</flux:button>
                @endif

                <flux:button
                    wire:click="deploy"
                    variant="primary"
                    size="sm"
                    icon="cloud-arrow-up"
                    :disabled="in_array($site->deploy_status, ['building', 'deploying'])"
                >
                    @if (in_array($site->deploy_status, ['building', 'deploying']))
                        {{ ucfirst($site->deploy_status) }}...
                    @else
                        Deploy Now
                    @endif
                </flux:button>
            </div>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <div class="rounded-lg bg-zinc-50 dark:bg-white/5 px-3 py-2">
                <flux:subheading size="sm">Status</flux:subheading>
                <div class="mt-1">
                    @switch($site->deploy_status)
                        @case('live') <flux:badge color="lime">Live</flux:badge> @break
                        @case('building') @case('deploying') <flux:badge color="yellow">{{ ucfirst($site->deploy_status) }}</flux:badge> @break
                        @case('failed') <flux:badge color="red">Failed</flux:badge> @break
                        @default <flux:badge color="zinc">Idle</flux:badge>
                    @endswitch
                </div>
            </div>
            <div class="rounded-lg bg-zinc-50 dark:bg-white/5 px-3 py-2">
                <flux:subheading size="sm">SSL</flux:subheading>
                <div class="mt-1">
                    @switch($site->ssl_status)
                        @case('active') <flux:badge color="lime">Active</flux:badge> @break
                        @case('expired') <flux:badge color="red">Expired</flux:badge> @break
                        @case('error') <flux:badge color="red">Error</flux:badge> @break
                        @default <flux:badge color="zinc">Pending</flux:badge>
                    @endswitch
                </div>
            </div>
            <div class="rounded-lg bg-zinc-50 dark:bg-white/5 px-3 py-2">
                <flux:subheading size="sm">Last Deploy</flux:subheading>
                <flux:text size="sm" class="mt-1">{{ $site->last_deployed_at?->diffForHumans() ?? 'Never' }}</flux:text>
            </div>
            <div class="rounded-lg bg-zinc-50 dark:bg-white/5 px-3 py-2">
                <flux:subheading size="sm">Domain</flux:subheading>
                <flux:text size="sm" class="mt-1 font-mono truncate">{{ $site->domain ?? 'Not set' }}</flux:text>
            </div>
        </div>
    </flux:card>

    <flux:card>
        <flux:heading size="sm" class="mb-4">Deploy History</flux:heading>

        <div class="space-y-1">
            @forelse ($deployLogs as $log)
                <div class="flex items-center gap-3 rounded-lg px-3 py-2 hover:bg-zinc-50 dark:hover:bg-white/5 transition">
                    @switch($log->status)
                        @case('success')
                            <flux:icon name="check-circle" variant="solid" class="size-5 text-lime-500 shrink-0" />
                            @break
                        @case('failed')
                            <flux:icon name="x-circle" variant="solid" class="size-5 text-red-500 shrink-0" />
                            @break
                        @default
                            <flux:icon name="arrow-path" class="size-5 text-amber-500 shrink-0 animate-spin" />
                    @endswitch

                    <div class="flex-1 min-w-0">
                        <flux:text size="sm">{{ $log->commit_message ?? ucfirst($log->status) . ' deploy' }}</flux:text>
                        <div class="flex items-center gap-3 mt-0.5">
                            <flux:text size="xs" class="font-mono">{{ $log->created_at->diffForHumans() }}</flux:text>
                            @if ($log->duration_ms)
                                <flux:text size="xs" class="font-mono">{{ $log->durationFormatted() }}</flux:text>
                            @endif
                            @if ($log->commit_sha)
                                <flux:text size="xs" class="font-mono">{{ Str::limit($log->commit_sha, 7, '') }}</flux:text>
                            @endif
                        </div>
                    </div>

                    <div class="flex items-center gap-1 shrink-0">
                        <flux:button wire:click="viewLog('{{ $log->id }}')" size="xs" variant="ghost">Log</flux:button>
                        @if ($log->isSuccess() && $log->snapshot_tag)
                            <flux:button wire:click="rollback('{{ $log->id }}')" wire:confirm="Rollback to this deploy?" size="xs" variant="ghost" class="text-amber-500">Rollback</flux:button>
                        @endif
                    </div>
                </div>
            @empty
                <div class="py-8 text-center">
                    <flux:subheading>No deploys yet. Click "Deploy Now" to start.</flux:subheading>
                </div>
            @endforelse
        </div>
    </flux:card>

    {{-- Log Viewer Modal --}}
    @if ($viewingLog)
        <flux:modal name="deploy-log" class="max-w-3xl" :show="true">
            <div class="space-y-4">
                <div>
                    <flux:heading size="lg">Deploy Log</flux:heading>
                    <flux:text size="xs" class="font-mono mt-1">
                        {{ $viewingLog->created_at->format('M j, Y H:i:s') }} · {{ $viewingLog->durationFormatted() }} · {{ $viewingLog->triggered_by }}
                        @if ($viewingLog->commit_sha) · {{ Str::limit($viewingLog->commit_sha, 7, '') }} @endif
                    </flux:text>
                </div>

                <div class="rounded-lg bg-zinc-50 dark:bg-zinc-900 p-4 max-h-96 overflow-y-auto">
                    <pre class="font-mono text-xs whitespace-pre-wrap">{{ $viewingLog->output_log ?? 'No output recorded.' }}</pre>
                </div>

                <flux:button wire:click="closeLog" variant="subtle">Close</flux:button>
            </div>
        </flux:modal>
    @endif
</div>
