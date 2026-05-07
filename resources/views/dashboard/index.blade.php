<x-layouts.app>
    <x-slot:title>Dashboard</x-slot:title>

    @php
        $user = auth()->user();
        $hour = (int) now()->format('H');
        $greeting = match(true) {
            $hour < 12 => 'Good morning',
            $hour < 17 => 'Good afternoon',
            default    => 'Good evening',
        };
    @endphp

    <div class="space-y-5">
        <div class="pk-page-head">
            <div>
                <h1 class="pk-page-title">{{ $greeting }}, {{ $user->name }}</h1>
                <p class="pk-page-sub">{{ now()->format('l, F j, Y') }}</p>
            </div>
            <x-ui.button href="{{ route('sites.index') }}" variant="outline" size="sm">View all sites</x-ui.button>
        </div>

        <div class="pk-stat-grid">
            <div class="stat">
                <p class="stat-label">Sites</p>
                <p class="stat-val tabular-nums">{{ $totalSites }}</p>
                @if ($sitesDown > 0)
                    <p class="stat-note text-red-400">{{ $sitesDown }} down</p>
                @elseif ($activeSites->whereNotNull('latestUptimeCheck')->isNotEmpty())
                    <p class="stat-note text-emerald-400">All online</p>
                @endif
            </div>
            <div class="stat">
                <p class="stat-label">Uptime</p>
                <p class="stat-val tabular-nums">{{ number_format($uptimePercent, 1) }}<span class="text-sm text-zinc-500">%</span></p>
            </div>
            <div class="stat">
                <p class="stat-label">Pages</p>
                <p class="stat-val tabular-nums">{{ $totalPages }}</p>
            </div>
            <div class="stat">
                <p class="stat-label">Messages</p>
                <p class="stat-val tabular-nums">{{ $unreadMessages }}</p>
                @if ($unreadMessages > 0)
                    <p class="stat-note text-sky-400">Unread</p>
                @endif
            </div>
            <div class="stat">
                <p class="stat-label">Errors</p>
                <p class="stat-val tabular-nums {{ $errorCount > 0 ? 'text-red-400' : '' }}">{{ $errorCount }}</p>
                @if ($errorCount > 0)
                    <p class="stat-note text-red-400">Needs attention</p>
                @endif
            </div>
            <div class="stat">
                <p class="stat-label">SEO Issues</p>
                <p class="stat-val tabular-nums {{ $seoIssueCount > 0 ? 'text-amber-400' : '' }}">{{ $seoIssueCount }}</p>
                @if ($seoIssueCount > 0)
                    <p class="stat-note text-amber-400">Needs attention</p>
                @endif
            </div>
        </div>

        <section class="dash-card">
            <div class="dash-card-head">
                <div>
                    <p class="text-sm text-zinc-400">Traffic — All sites</p>
                    <p class="text-[11px] text-zinc-500">Last 30 days</p>
                </div>
                <div class="text-right">
                    <p class="text-xl font-semibold tabular-nums text-zinc-100">{{ number_format($trafficVisitors) }} <span class="text-sm font-normal text-zinc-500">visitors</span></p>
                    <p class="text-[11px] text-zinc-500">{{ $trafficSeries->first()['label'] }} - {{ $trafficSeries->last()['label'] }}</p>
                </div>
            </div>

            <div class="chart-shell rounded-lg border border-zinc-800/90 bg-[#141414] p-3">
                <svg class="h-52 w-full" viewBox="0 0 {{ $vbW }} {{ $vbH }}" preserveAspectRatio="none" role="img" aria-label="Traffic trend for all sites">
                    @foreach ([0, 1, 2, 3] as $line)
                        <line x1="{{ $pad }}" y1="{{ $pad + (($line / 3) * $plotH) }}" x2="{{ $vbW - $pad }}" y2="{{ $pad + (($line / 3) * $plotH) }}" stroke="rgb(39 39 42)" stroke-width="1" vector-effect="non-scaling-stroke" />
                    @endforeach
                    <defs>
                        <linearGradient id="dashboardTrafficFill" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="rgb(45 212 191)" stop-opacity="0.42" />
                            <stop offset="100%" stop-color="rgb(45 212 191)" stop-opacity="0" />
                        </linearGradient>
                    </defs>
                    <path d="{{ $areaD }}" fill="url(#dashboardTrafficFill)" />
                    <path d="{{ $lineD }}" fill="none" stroke="rgb(45 212 191)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke" />
                </svg>
                <div class="mt-2 flex justify-between px-1 text-[10px] text-zinc-500">
                    <span>{{ $trafficSeries->first()['label'] }}</span>
                    <span>{{ $trafficSeries->last()['label'] }}</span>
                </div>
            </div>
        </section>

        <div class="grid grid-cols-1 gap-5 lg:grid-cols-2 lg:gap-6">
            @livewire('dashboard.alerts-panel')
            @livewire('dashboard.activity-feed')
        </div>

        @livewire('dashboard.site-health-table')

        @if ($siteInsights->isNotEmpty())
            <div class="space-y-5">
                @foreach ($siteInsights as $insight)
                    <div class="space-y-4">
                        <section class="dash-card">
                            <div class="dash-card-head">
                                <p class="text-sm text-zinc-300">{{ $insight['site']->name }} — Uptime</p>
                                <p class="text-sm font-semibold tabular-nums {{ $insight['uptime_percent'] >= 99.8 ? 'text-emerald-400' : ($insight['uptime_percent'] >= 99 ? 'text-amber-400' : 'text-red-400') }}">
                                    {{ number_format($insight['uptime_percent'], 1) }}%
                                </p>
                            </div>
                            <div class="flex h-12 items-end gap-px">
                                @foreach ($insight['daily_bars'] as $bar)
                                    <span @class([
                                        'min-h-[6px] flex-1 rounded-sm',
                                        'bg-emerald-400' => $bar === 'up',
                                        'bg-amber-400' => $bar === 'degraded',
                                        'bg-red-400' => $bar === 'down',
                                        'bg-zinc-700' => $bar === 'unknown',
                                    ])></span>
                                @endforeach
                            </div>
                            <div class="mt-2 flex gap-4 text-[10px] text-zinc-500">
                                <span class="inline-flex items-center gap-1"><span class="size-2 rounded-[2px] bg-emerald-400"></span>Up</span>
                                <span class="inline-flex items-center gap-1"><span class="size-2 rounded-[2px] bg-amber-400"></span>Degraded</span>
                                <span class="inline-flex items-center gap-1"><span class="size-2 rounded-[2px] bg-red-400"></span>Down</span>
                            </div>
                        </section>

                        <section class="dash-card">
                            <div class="dash-card-head">
                                <p class="text-sm text-zinc-300">{{ $insight['site']->name }} — Response time</p>
                                <p class="text-[11px] text-zinc-500 tabular-nums">avg {{ number_format($insight['avg_response']) }}ms &nbsp; p95 {{ number_format($insight['p95_response']) }}ms</p>
                            </div>
                            @php
                                $responseSeries = $insight['response_series'];
                                $responseMax = max(1, $responseSeries->max() ?? 0);
                                $responseCount = max(1, $responseSeries->count() - 1);
                                $rW = 390;
                                $rH = 120;
                                $rPad = 8;
                                $rPlotW = $rW - ($rPad * 2);
                                $rPlotH = $rH - ($rPad * 2);
                                $rPoints = [];
                                foreach ($responseSeries as $i => $ms) {
                                    $x = $rPad + (($i / $responseCount) * $rPlotW);
                                    $y = $rPad + $rPlotH - (($ms / $responseMax) * $rPlotH);
                                    $rPoints[] = round($x, 2) . ',' . round($y, 2);
                                }
                                $responsePath = empty($rPoints) ? '' : 'M ' . implode(' L ', $rPoints);
                            @endphp
                            <div class="rounded-lg border border-zinc-800/90 bg-[#141414] p-2">
                                @if (empty($rPoints))
                                    <p class="py-6 text-center text-xs text-zinc-500">No response samples yet.</p>
                                @else
                                    <svg class="h-24 w-full" viewBox="0 0 {{ $rW }} {{ $rH }}" preserveAspectRatio="none">
                                        <line x1="{{ $rPad }}" y1="{{ $rPad }}" x2="{{ $rW - $rPad }}" y2="{{ $rPad }}" stroke="rgb(63 63 70)" stroke-width="1" stroke-dasharray="3 3" />
                                        <line x1="{{ $rPad }}" y1="{{ $rPad + ($rPlotH / 2) }}" x2="{{ $rW - $rPad }}" y2="{{ $rPad + ($rPlotH / 2) }}" stroke="rgb(63 63 70)" stroke-width="1" stroke-dasharray="3 3" />
                                        <line x1="{{ $rPad }}" y1="{{ $rPad + $rPlotH }}" x2="{{ $rW - $rPad }}" y2="{{ $rPad + $rPlotH }}" stroke="rgb(63 63 70)" stroke-width="1" stroke-dasharray="3 3" />
                                        <path d="{{ $responsePath }}" fill="none" stroke="rgb(94 234 212)" stroke-width="1.8" stroke-linejoin="round" />
                                    </svg>
                                @endif
                            </div>
                        </section>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="grid grid-cols-1 gap-5 lg:grid-cols-3 lg:gap-6">
            @livewire('dashboard.seo-issues-panel')
            @livewire('dashboard.upcoming-panel')
            @livewire('dashboard.expenses-panel')
        </div>
    </div>
</x-layouts.app>
