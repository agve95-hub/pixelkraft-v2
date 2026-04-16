<x-layouts.app>
    <x-slot:title>{{ $site->name }} - Analytics</x-slot:title>

    @php
        $trendLabel = is_null($trafficTrendPercent)
            ? 'No baseline data'
            : (($trafficTrendPercent >= 0 ? '+' : '') . $trafficTrendPercent . '% vs prev');
        $trendColor = is_null($trafficTrendPercent)
            ? 'var(--zinc-500)'
            : ($trafficTrendPercent >= 0 ? 'var(--accent)' : 'var(--red)');
    @endphp

    <div>
        <a href="{{ route('sites.show', $site) }}" class="back-link">
            <x-icons.arrow />
            <span>{{ $site->name }}</span>
        </a>

        <div class="page-head">
            <div>
                <div class="page-title">{{ $site->name }} - Analytics</div>
                <div class="page-sub">Traffic, uptime, and performance over the last 30 days</div>
            </div>

            <select class="form-input" style="width:auto;padding:6px 32px 6px 12px;font-size:12px;cursor:not-allowed;opacity:0.45" disabled title="Date range filtering coming soon">
                <option>Last 30 days</option>
            </select>
        </div>

        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:1px;background:rgba(255,255,255,0.1);border-radius:12px;overflow:hidden;margin-bottom:24px">
            <div class="stat">
                <div class="stat-label">Visitors</div>
                <div class="stat-val">{{ number_format($trafficTotal) }}</div>
                <div class="stat-note" style="color:{{ $trendColor }}">{{ $trendLabel }}</div>
            </div>
            <div class="stat">
                <div class="stat-label">Uptime</div>
                <div class="stat-val">{{ rtrim(rtrim(number_format($uptimePercent, 1, '.', ''), '0'), '.') }}<span style="font-size:13px;color:var(--zinc-500)">%</span></div>
                <div class="stat-note">
                    {{ $upDays }} days up
                    @if ($degradedDays)
                        , {{ $degradedDays }} degraded
                    @endif
                    @if ($downDays)
                        , <span style="color:var(--red)">{{ $downDays }} down</span>
                    @endif
                </div>
            </div>
            <div class="stat">
                <div class="stat-label">Avg response</div>
                <div class="stat-val">{{ $avgResponseMs }}<span style="font-size:13px;color:var(--zinc-500)">ms</span></div>
                <div class="stat-note">P95: {{ $p95ResponseMs }}ms</div>
            </div>
            <div class="stat">
                <div class="stat-label">Deploys</div>
                <div class="stat-val">{{ $deployCount }}</div>
                <div class="stat-note">Recent deploy history</div>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px">
            <div class="dash-card">
                <div class="dash-card-head">
                    <div class="dash-card-title">First-party events</div>
                    <span style="font-family:var(--mono);font-size:12px;color:var(--zinc-400)">{{ number_format($eventSummary['total_events']) }} total</span>
                </div>
                <div class="stats stats-3" style="margin-bottom:14px">
                    <div class="stat">
                        <div class="stat-label">Page views</div>
                        <div class="stat-val-sm">{{ number_format($eventSummary['page_views']) }}</div>
                    </div>
                    <div class="stat">
                        <div class="stat-label">Forms</div>
                        <div class="stat-val-sm">{{ number_format($eventSummary['forms']) }}</div>
                    </div>
                    <div class="stat">
                        <div class="stat-label">Interactions</div>
                        <div class="stat-val-sm">{{ number_format($eventSummary['interactions']) }}</div>
                    </div>
                </div>

                <div class="thread-list">
                    @forelse ($eventSummary['top_events'] as $event)
                        <div class="thread" style="cursor:default">
                            <div>
                                <div class="thread-from">{{ $event['event_name'] }}</div>
                                <div class="thread-preview">Captured by pixelkraft tracker</div>
                            </div>
                            <div class="thread-time">{{ number_format($event['count']) }}</div>
                        </div>
                    @empty
                        <div class="empty">
                            <div class="empty-icon"><x-icons.chart /></div>
                            No custom events yet
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="dash-card">
                <div class="dash-card-head">
                    <div class="dash-card-title">Release status</div>
                    <span style="font-family:var(--mono);font-size:12px;color:var(--zinc-400)">{{ $releaseCount }} release{{ $releaseCount === 1 ? '' : 's' }}</span>
                </div>
                <div class="stats stats-2" style="margin-bottom:14px">
                    <div class="stat">
                        <div class="stat-label">Current</div>
                        <div class="stat-val-sm">{{ $currentRelease?->status ? ucfirst($currentRelease->status) : 'None' }}</div>
                        <div class="stat-note">{{ $currentRelease?->activated_at?->diffForHumans() ?? 'No active release' }}</div>
                    </div>
                    <div class="stat">
                        <div class="stat-label">Tracking</div>
                        <div class="stat-val-sm">{{ $currentRelease?->tracking_version ?: 'Pending' }}</div>
                        <div class="stat-note">{{ $currentRelease?->artifact_path ? 'artifact ready' : 'no artifact recorded' }}</div>
                    </div>
                </div>

                <div class="issue-item" style="border-bottom:none;padding-top:0">
                    <div class="issue-icon issue-icon-blue"><x-icons.chart /></div>
                    <div>
                        <div class="issue-text">Current release commit: {{ $currentRelease?->source_commit_sha ? \Illuminate\Support\Str::limit($currentRelease->source_commit_sha, 12, '') : 'n/a' }}</div>
                        <div class="issue-meta">Source branch: {{ $currentRelease?->source_branch ?: $site->branch }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="dash-card" style="margin-bottom:20px">
            <div class="dash-card-head">
                <div class="dash-card-title">Visitors</div>
                <span style="font-family:var(--mono);font-size:12px;color:var(--zinc-400)">{{ number_format($trafficTotal) }} total</span>
            </div>
            <div class="chart-container">
                @if ($trafficChart['line_path'] === '')
                    <div class="empty">
                        <div class="empty-icon"><x-icons.chart /></div>
                        No traffic data yet
                    </div>
                @else
                    <svg viewBox="0 0 {{ $trafficChart['width'] }} {{ $trafficChart['height'] }}" preserveAspectRatio="none">
                        <defs>
                            <linearGradient id="analyticsTrafficFill" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="rgb(52 211 153)" stop-opacity="0.28" />
                                <stop offset="100%" stop-color="rgb(52 211 153)" stop-opacity="0" />
                            </linearGradient>
                        </defs>
                        <path d="{{ $trafficChart['area_path'] }}" fill="url(#analyticsTrafficFill)" />
                        <path d="{{ $trafficChart['line_path'] }}" fill="none" stroke="rgb(52 211 153)" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                @endif
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px">
            <div class="dash-card">
                <div class="dash-card-head">
                    <div class="dash-card-title">Uptime</div>
                    <span style="font-family:var(--mono);font-size:12px;color:{{ $uptimePercent >= 99.9 ? 'var(--accent)' : 'var(--amber)' }}">{{ rtrim(rtrim(number_format($uptimePercent, 1, '.', ''), '0'), '.') }}%</span>
                </div>
                <div class="uptime-bar">
                    @foreach ($dailyBars as $bar)
                        <span class="uptime-bar-seg" style="background:
                            @switch($bar)
                                @case('up')
                                    var(--accent)
                                    @break
                                @case('degraded')
                                    var(--amber)
                                    @break
                                @case('down')
                                    var(--red)
                                    @break
                                @default
                                    rgba(255,255,255,0.12)
                            @endswitch
                        "></span>
                    @endforeach
                </div>
                <div class="uptime-legend">
                    <span><span class="uptime-legend-dot" style="background:var(--accent)"></span>Up</span>
                    <span><span class="uptime-legend-dot" style="background:var(--amber)"></span>Degraded</span>
                    <span><span class="uptime-legend-dot" style="background:var(--red)"></span>Down</span>
                </div>
            </div>

            <div class="dash-card">
                <div class="dash-card-head">
                    <div class="dash-card-title">Response time</div>
                    <span style="font-family:var(--mono);font-size:12px;color:var(--zinc-400)">avg {{ $avgResponseMs }}ms - p95 {{ $p95ResponseMs }}ms</span>
                </div>
                <div class="chart-container">
                    @if ($responseChart['path'] === '')
                        <div class="empty">
                            <div class="empty-icon"><x-icons.chart /></div>
                            No response samples yet
                        </div>
                    @else
                        <svg viewBox="0 0 {{ $responseChart['width'] }} {{ $responseChart['height'] }}" preserveAspectRatio="none">
                            <path d="{{ $responseChart['path'] }}" fill="none" stroke="rgb(52 211 153)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    @endif
                </div>
            </div>
        </div>

        <div class="dash-card">
            <div class="dash-card-head">
                <div class="dash-card-title">Recent deploys</div>
            </div>
            <div class="deploy-list">
                @forelse ($deploys as $deploy)
                    <div class="deploy-item">
                        <span class="pill {{ $deploy->isSuccess() ? 'pill-green' : 'pill-red' }}" style="font-size:10px;width:fit-content">
                            {{ $deploy->isSuccess() ? 'Success' : 'Failed' }}
                        </span>
                        <span class="deploy-hash">{{ $deploy->hash ?: 'snapshot' }}</span>
                        <span class="deploy-dur">{{ $deploy->duration }}</span>
                        <span class="deploy-time">{{ $deploy->created_at?->diffForHumans() ?? 'recently' }}</span>
                        <div style="display:flex;gap:8px">
                            <button type="button" class="btn-ghost btn btn-sm" disabled title="Deploy log viewer coming soon" style="opacity:0.45;cursor:not-allowed">Log</button>
                        </div>
                    </div>
                @empty
                    <div class="empty">
                        <div class="empty-icon"><x-icons.chart /></div>
                        No deploy history yet
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</x-layouts.app>
