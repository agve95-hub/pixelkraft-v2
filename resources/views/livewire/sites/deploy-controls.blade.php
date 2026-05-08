@php
    $isDeploying = $site->deploy_status?->isActive() ?? false;
    $_ds = $site->deploy_status?->value ?? 'draft';
    $statusBadge = match ($_ds) { 'live', 'success' => 'success', 'building', 'deploying', 'queued' => 'warning', 'failed' => 'destructive', default => 'default' };
    $statusLabel  = $site->status;
    $sslBadge    = match ((string) $site->ssl_status) { 'active' => 'success', 'expired', 'error' => 'destructive', default => 'warning' };
    $sslLabel    = match ((string) $site->ssl_status) { 'active' => 'Active', 'expired' => 'Expired', 'error' => 'Error', default => 'Pending' };
@endphp

<div wire:poll.5s class="space-y-4">
    {{-- Deploy controls card --}}
    <x-ui.card>
        <x-ui.card-header>
            <div>
                <x-ui.card-title><x-icons.zap /> Deploy &amp; infrastructure</x-ui.card-title>
                <x-ui.card-description>Trigger a new deploy, provision the domain, and watch the latest activity.</x-ui.card-description>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if ($site->domain && ! $site->nginx_conf_path)
                    <flux:button type="button" wire:click="setupDomain" wire:target="setupDomain" wire:loading.attr="disabled" variant="outline" size="sm" icon="globe-alt">
                        <span wire:loading.remove wire:target="setupDomain">Setup domain &amp; SSL</span>
                        <span wire:loading wire:target="setupDomain">Setting up...</span>
                    </flux:button>
                @endif
                <flux:button type="button" wire:click="deploy" wire:target="deploy" wire:loading.attr="disabled" @disabled($isDeploying) variant="primary" size="sm" icon="bolt">
                    @if ($isDeploying)
                        {{ $statusLabel }}...
                    @else
                        <span wire:loading.remove wire:target="deploy">Deploy now</span>
                        <span wire:loading wire:target="deploy">Starting...</span>
                    @endif
                </flux:button>
            </div>
        </x-ui.card-header>

        <div class="stats stats-4">
            <div class="stat">
                <p class="stat-label">Status</p>
                <div class="mt-2"><x-ui.badge variant="{{ $statusBadge }}" dot>{{ $statusLabel }}</x-ui.badge></div>
                <p class="stat-note">{{ $site->deployLogs()->count() }} deploys recorded</p>
            </div>
            <div class="stat">
                <p class="stat-label">SSL</p>
                <div class="mt-2"><x-ui.badge variant="{{ $sslBadge }}" dot>{{ $sslLabel }}</x-ui.badge></div>
                <p class="stat-note">{{ $site->domain ?: 'Domain not connected' }}</p>
            </div>
            <div class="stat">
                <p class="stat-label">Last deploy</p>
                <p class="stat-val-sm mt-1">{{ $site->last_deployed_at?->diffForHumans() ?? 'Never' }}</p>
                <p class="stat-note">{{ $site->branch ?: 'No branch set' }}</p>
            </div>
            <div class="stat">
                <p class="stat-label">Deploy path</p>
                <p class="stat-val-sm mt-1 font-mono text-xs">{{ $productionTarget?->deploy_path ?: $site->deploy_path ?: 'Not configured' }}</p>
                <p class="stat-note">{{ $productionTarget?->release_strategy ? strtoupper($productionTarget->release_strategy) . ' releases' : ($site->repo_url ? 'Git connected' : 'Repo missing') }}</p>
            </div>
        </div>

        <div class="mt-4 grid gap-3 sm:grid-cols-3">
            <div class="rounded-lg border border-zinc-800/70 bg-zinc-950/30 p-3">
                <p class="stat-label">Current release</p>
                <p class="stat-val-sm mt-1 font-mono text-xs">{{ $currentRelease?->source_commit_sha ? \Illuminate\Support\Str::limit($currentRelease->source_commit_sha, 10, '') : 'No active release' }}</p>
                <p class="stat-note">{{ $currentRelease?->activated_at?->diffForHumans() ?? 'Waiting for first deploy' }}</p>
            </div>
            <div class="rounded-lg border border-zinc-800/70 bg-zinc-950/30 p-3">
                <p class="stat-label">Target runtime</p>
                <p class="stat-val-sm mt-1">{{ $productionTarget?->runtime_type ?: 'static' }}</p>
                <p class="stat-note">{{ $productionTarget?->host ?: ($site->ssh_host ?: 'Host not set') }}</p>
            </div>
            <div class="rounded-lg border border-zinc-800/70 bg-zinc-950/30 p-3">
                <p class="stat-label">Tracking</p>
                <p class="stat-val-sm mt-1">{{ $trackingInstallation?->provider ? ucfirst($trackingInstallation->provider) : 'Not installed' }}</p>
                <p class="stat-note">{{ $trackingInstallation?->script_route ?: 'Deploy-time injection pending' }}</p>
            </div>
        </div>
    </x-ui.card>

    {{-- Deploy history --}}
    <x-ui.card padding="flush">
        <x-ui.card-header class="px-[18px] pt-4 pb-3">
            <x-ui.card-title><x-icons.report /> Deploy history</x-ui.card-title>
            <span class="text-xs text-zinc-500">Latest 15 runs</span>
        </x-ui.card-header>

        @if ($deployLogs->isEmpty())
            <div class="px-[18px] pb-4">
                <x-ui.empty icon="bolt" title="No deploys yet" description="Trigger the first deploy to populate this history." />
            </div>
        @else
            @foreach ($deployLogs as $log)
                @php
                    $logBadge = match ((string) $log->status) { 'success', 'live' => 'success', 'failed' => 'destructive', default => 'warning' };
                    $logLabel = match ((string) $log->status) { 'success' => 'Success', 'failed' => 'Failed', 'deploying' => 'Deploying', 'building' => 'Building', 'queued' => 'Queued', default => ucfirst((string) $log->status) };
                    $logIconClass = match ($logBadge) { 'success' => 'issue-icon-green', 'destructive' => 'issue-icon-red', default => 'issue-icon-yellow' };
                @endphp
                <div class="deploy-item">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="issue-icon {{ $logIconClass }}">
                            @if ($logBadge === 'success') <x-icons.check />
                            @elseif ($logBadge === 'destructive') <x-icons.alert />
                            @else <x-icons.clock /> @endif
                        </span>
                        <x-ui.badge variant="{{ $logBadge }}">{{ $logLabel }}</x-ui.badge>
                    </div>
                    <span class="deploy-hash">{{ $log->hash ?: 'manual' }}</span>
                    <span class="deploy-dur">{{ $log->durationFormatted() }}</span>
                    <div class="min-w-0">
                        <p class="truncate text-[13px] text-zinc-200">{{ $log->commit_message ?: ucfirst((string) $log->status) . ' deploy' }}</p>
                        <p class="text-[11px] text-zinc-500">{{ $log->triggered_by ?: 'manual' }}@if ($log->created_at) &mdash; {{ $log->created_at->format('M j, Y H:i') }}@endif</p>
                    </div>
                    <div class="flex items-center justify-end gap-2 flex-wrap">
                        <span class="deploy-time">{{ $log->created_at?->diffForHumans() ?? 'recently' }}</span>
                        <x-ui.button type="button" wire:click="viewLog('{{ $log->id }}')" size="xs" variant="ghost">Log</x-ui.button>
                        @if ($log->isSuccess() && $log->snapshot_tag)
                            <x-ui.button type="button" wire:click="rollback('{{ $log->id }}')" wire:confirm="Rollback to this deploy?" size="xs" variant="outline" class="border-amber-500/30 text-amber-400 hover:bg-amber-500/10">Rollback</x-ui.button>
                        @endif
                    </div>
                </div>
            @endforeach
        @endif
    </x-ui.card>

    {{-- Log viewer --}}
    @if ($viewingLog)
        <x-ui.card>
            <x-ui.card-header>
                <x-ui.card-title><x-icons.file /> Deploy log</x-ui.card-title>
                <x-ui.button type="button" wire:click="closeLog" variant="ghost" size="sm">Close</x-ui.button>
            </x-ui.card-header>
            <p class="mb-3 font-mono text-xs text-zinc-500">
                {{ $viewingLog->created_at?->format('M j, Y H:i:s') ?? 'Unknown time' }}
                &mdash; {{ $viewingLog->durationFormatted() }}
                &mdash; {{ $viewingLog->triggered_by ?: 'manual' }}
                @if ($viewingLog->commit_sha) &mdash; {{ \Illuminate\Support\Str::limit($viewingLog->commit_sha, 7, '') }} @endif
            </p>
            <div class="max-h-[420px] overflow-auto rounded-lg border border-zinc-800/70 bg-zinc-950/50 p-4">
                <pre class="font-mono text-xs leading-relaxed text-zinc-300 whitespace-pre-wrap">{{ $viewingLog->output_log ?? 'No output recorded.' }}</pre>
            </div>
        </x-ui.card>
    @endif
</div>
