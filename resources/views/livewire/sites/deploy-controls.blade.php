@php
    $isDeploying = $site->deploy_status?->isActive() ?? false;

    $_ds = $site->deploy_status?->value ?? 'draft';
    $statusColor = match ($_ds) {
        'live', 'success' => 'green',
        'building', 'deploying', 'queued' => 'yellow',
        'failed' => 'red',
        default => 'blue',
    };

    $statusLabel = $site->status; // computed attribute on Site model

    $sslColor = match ((string) $site->ssl_status) {
        'active' => 'green',
        'expired', 'error' => 'red',
        default => 'yellow',
    };

    $sslLabel = match ((string) $site->ssl_status) {
        'active' => 'Active',
        'expired' => 'Expired',
        'error' => 'Error',
        default => 'Pending',
    };
@endphp

<div wire:poll.5s style="display:grid;gap:16px">
    <div class="dash-card">
        <div class="dash-card-head" style="align-items:flex-start">
            <div>
                <div class="dash-card-title">
                    <x-icons.zap />
                    <span>Deploy &amp; infrastructure</span>
                </div>
                <div style="font-size:12px;color:var(--zinc-500);margin-top:4px">
                    Trigger a new deploy, provision the domain, and watch the latest activity without leaving the project page.
                </div>
            </div>

            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                @if ($site->domain && ! $site->nginx_conf_path)
                    <button
                        type="button"
                        wire:click="setupDomain"
                        wire:target="setupDomain"
                        wire:loading.attr="disabled"
                        class="btn btn-sm"
                    >
                        <x-icons.globe />
                        <span wire:loading.remove wire:target="setupDomain">Setup domain &amp; SSL</span>
                        <span wire:loading wire:target="setupDomain">Setting up...</span>
                    </button>
                @endif

                <button
                    type="button"
                    wire:click="deploy"
                    wire:target="deploy"
                    wire:loading.attr="disabled"
                    @disabled($isDeploying)
                    class="btn btn-accent btn-sm"
                >
                    <x-icons.zap />
                    @if ($isDeploying)
                        <span>{{ $statusLabel }}...</span>
                    @else
                        <span wire:loading.remove wire:target="deploy">Deploy now</span>
                        <span wire:loading wire:target="deploy">Starting...</span>
                    @endif
                </button>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:1px;background:rgba(255,255,255,0.1);border-radius:12px;overflow:hidden">
            <div class="stat">
                <div class="stat-label">Status</div>
                <div style="margin-top:4px;margin-bottom:4px">
                    <x-pill :color="$statusColor">{{ $statusLabel }}</x-pill>
                </div>
                <div class="stat-note">{{ $site->deployLogs()->count() }} deploys recorded</div>
            </div>

            <div class="stat">
                <div class="stat-label">SSL</div>
                <div style="margin-top:4px;margin-bottom:4px">
                    <x-pill :color="$sslColor">{{ $sslLabel }}</x-pill>
                </div>
                <div class="stat-note">{{ $site->domain ?: 'Domain not connected' }}</div>
            </div>

            <div class="stat">
                <div class="stat-label">Last deploy</div>
                <div class="stat-val-sm" style="margin-top:6px;font-size:12px">{{ $site->last_deployed_at?->diffForHumans() ?? 'Never' }}</div>
                <div class="stat-note">{{ $site->branch ?: 'No branch set' }}</div>
            </div>

            <div class="stat">
                <div class="stat-label">Deploy path</div>
                <div class="stat-val-sm" style="margin-top:6px;font-size:12px">{{ $productionTarget?->deploy_path ?: $site->deploy_path ?: 'Not configured' }}</div>
                <div class="stat-note">{{ $productionTarget?->release_strategy ? strtoupper($productionTarget->release_strategy) . ' releases' : ($site->repo_url ? 'Git connected' : 'Repo missing') }}</div>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-top:16px">
            <div style="border:1px solid rgba(255,255,255,0.08);border-radius:12px;padding:14px;background:rgba(255,255,255,0.03)">
                <div class="stat-label">Current release</div>
                <div class="stat-val-sm" style="margin-top:6px;font-size:12px">
                    {{ $currentRelease?->source_commit_sha ? \Illuminate\Support\Str::limit($currentRelease->source_commit_sha, 10, '') : 'No active release' }}
                </div>
                <div class="stat-note">{{ $currentRelease?->activated_at?->diffForHumans() ?? 'Waiting for first successful deploy' }}</div>
            </div>

            <div style="border:1px solid rgba(255,255,255,0.08);border-radius:12px;padding:14px;background:rgba(255,255,255,0.03)">
                <div class="stat-label">Target runtime</div>
                <div class="stat-val-sm" style="margin-top:6px;font-size:12px">{{ $productionTarget?->runtime_type ?: 'static' }}</div>
                <div class="stat-note">{{ $productionTarget?->host ?: ($site->ssh_host ?: 'Local/VPS host not set') }}</div>
            </div>

            <div style="border:1px solid rgba(255,255,255,0.08);border-radius:12px;padding:14px;background:rgba(255,255,255,0.03)">
                <div class="stat-label">Tracking</div>
                <div class="stat-val-sm" style="margin-top:6px;font-size:12px">{{ $trackingInstallation?->provider ? ucfirst($trackingInstallation->provider) : 'Not installed' }}</div>
                <div class="stat-note">{{ $trackingInstallation?->script_route ?: 'Deploy-time injection pending' }}</div>
            </div>
        </div>
    </div>

    <div class="dash-card">
        <div class="dash-card-head">
            <div class="dash-card-title">
                <x-icons.report />
                <span>Deploy history</span>
            </div>
            <span style="font-size:11px;color:var(--zinc-500)">Latest 15 runs</span>
        </div>

        @if ($deployLogs->isEmpty())
            <div class="empty" style="border:1px solid rgba(255,255,255,0.08);border-radius:12px">
                <div class="empty-icon"><x-icons.zap /></div>
                No deploys yet
                <span style="font-size:12px">Trigger the first deploy to populate this history</span>
            </div>
        @else
            <div class="deploy-list">
                @foreach ($deployLogs as $log)
                    @php
                        $logColor = match ((string) $log->status) {
                            'success', 'live' => 'green',
                            'failed' => 'red',
                            default => 'yellow',
                        };

                        $logLabel = match ((string) $log->status) {
                            'success' => 'Success',
                            'failed' => 'Failed',
                            'deploying' => 'Deploying',
                            'building' => 'Building',
                            'queued' => 'Queued',
                            default => ucfirst((string) $log->status),
                        };
                    @endphp

                    <div class="deploy-item">
                        <div style="display:flex;align-items:center;gap:8px;min-width:0">
                            <div class="issue-icon issue-icon-{{ $logColor }}">
                                @if ($logColor === 'green')
                                    <x-icons.check />
                                @elseif ($logColor === 'red')
                                    <x-icons.alert />
                                @else
                                    <x-icons.clock />
                                @endif
                            </div>
                            <x-pill :color="$logColor" style="font-size:10px;width:fit-content">{{ $logLabel }}</x-pill>
                        </div>

                        <span class="deploy-hash">{{ $log->hash ?: 'manual' }}</span>
                        <span class="deploy-dur">{{ $log->durationFormatted() }}</span>

                        <div style="min-width:0">
                            <div style="font-size:13px;color:rgba(255,255,255,0.78);white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                                {{ $log->commit_message ?: ucfirst((string) $log->status) . ' deploy' }}
                            </div>
                            <div style="font-size:11px;color:var(--zinc-500);margin-top:2px">
                                {{ $log->triggered_by ?: 'manual' }}
                                @if ($log->created_at)
                                    - {{ $log->created_at->format('M j, Y H:i') }}
                                @endif
                            </div>
                        </div>

                        <div style="display:flex;align-items:center;justify-content:flex-end;gap:8px;flex-wrap:wrap">
                            <span class="deploy-time">{{ $log->created_at?->diffForHumans() ?? 'recently' }}</span>
                            <button type="button" wire:click="viewLog('{{ $log->id }}')" class="btn btn-sm" style="font-size:11px;padding:4px 10px">Log</button>
                            @if ($log->isSuccess() && $log->snapshot_tag)
                                <button
                                    type="button"
                                    wire:click="rollback('{{ $log->id }}')"
                                    wire:confirm="Rollback to this deploy?"
                                    class="btn btn-sm"
                                    style="font-size:11px;padding:4px 10px;color:var(--amber);border-color:rgba(245,158,11,0.3)"
                                >
                                    Rollback
                                </button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    @if ($viewingLog)
        <div class="dash-card">
            <div class="dash-card-head">
                <div class="dash-card-title">
                    <x-icons.file />
                    <span>Deploy log</span>
                </div>
                <button type="button" wire:click="closeLog" class="btn btn-sm">Close</button>
            </div>

            <div style="font-size:12px;color:var(--zinc-500);margin-bottom:14px;font-family:var(--mono)">
                {{ $viewingLog->created_at?->format('M j, Y H:i:s') ?? 'Unknown time' }} -
                {{ $viewingLog->durationFormatted() }} -
                {{ $viewingLog->triggered_by ?: 'manual' }}
                @if ($viewingLog->commit_sha)
                    - {{ \Illuminate\Support\Str::limit($viewingLog->commit_sha, 7, '') }}
                @endif
            </div>

            <div style="background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);border-radius:12px;padding:16px;max-height:420px;overflow:auto">
                <pre style="margin:0;font-family:var(--mono);font-size:12px;line-height:1.6;color:rgba(255,255,255,0.76);white-space:pre-wrap">{{ $viewingLog->output_log ?? 'No output recorded.' }}</pre>
            </div>
        </div>
    @endif
</div>
