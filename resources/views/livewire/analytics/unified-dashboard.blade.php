<div class="space-y-6" wire:poll.30s>
    <div class="ui-page-head">
        <div>
            <h1 class="ui-page-title">Analytics &amp; Performance</h1>
            <p class="ui-page-sub">{{ $stats['mode'] === 'site' ? 'Viewing ' . $stats['site_name'] : 'Portfolio-wide traffic, uptime, and deployment health' }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <flux:select wire:model.live="siteId" class="text-sm w-52">
                <flux:select.option value="">All Sites</flux:select.option>
                @foreach ($sites as $site)
                    <flux:select.option value="{{ $site->id }}">{{ $site->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="days" class="text-sm w-40">
                <flux:select.option value="7">Last 7 days</flux:select.option>
                <flux:select.option value="30">Last 30 days</flux:select.option>
                <flux:select.option value="90">Last 90 days</flux:select.option>
            </flux:select>
        </div>
    </div>

    {{-- Stat strip --}}
    <div class="stats stats-3">
        <div class="stat">
            <p class="stat-label">Visitors</p>
            <p class="stat-val mt-2 tabular-nums">{{ number_format($stats['total_visitors']) }}</p>
            <p class="stat-note">{{ $stats['traffic_label'] ?? 'Traffic' }} · selected period</p>
        </div>
        <div class="stat">
            <p class="stat-label">Pageviews</p>
            <p class="stat-val mt-2 tabular-nums">{{ number_format($stats['total_pageviews']) }}</p>
            <p class="stat-note">Total page impressions</p>
        </div>
        <div class="stat">
            <p class="stat-label">Online Users Today</p>
            <p class="stat-val mt-2 tabular-nums">{{ number_format($stats['users_today']) }}</p>
            <p class="stat-note">Visitors recorded today</p>
        </div>
        <div class="stat">
            <p class="stat-label">Runtime</p>
            <div class="mt-2 flex items-baseline gap-2">
                <p class="stat-val tabular-nums">{{ number_format($stats['runtime']['runtime_percent'], 2) }}%</p>
                @php $rtVariant = $stats['runtime']['runtime_percent'] >= 99.5 ? 'success' : ($stats['runtime']['runtime_percent'] >= 97 ? 'warning' : 'destructive'); @endphp
                <x-ui.badge variant="{{ $rtVariant }}">{{ $stats['runtime']['runtime_percent'] >= 99.5 ? 'Excellent' : 'Needs attention' }}</x-ui.badge>
            </div>
            <p class="stat-note">Calculated from uptime checks</p>
        </div>
        <div class="stat">
            <p class="stat-label">Downtime</p>
            <p class="stat-val mt-2 tabular-nums">{{ number_format($stats['runtime']['downtime_minutes']) }}m</p>
            <p class="stat-note">{{ number_format($stats['runtime']['downtime_hours'], 1) }}h total in selected period</p>
        </div>
        <div class="stat">
            <p class="stat-label">Deploy Success</p>
            <div class="mt-2 flex items-baseline gap-2">
                <p class="stat-val tabular-nums">{{ number_format($stats['deploy']['success_rate'], 1) }}%</p>
                <span class="text-xs text-zinc-500">({{ $stats['deploy']['successful'] }}/{{ $stats['deploy']['total'] }})</span>
            </div>
            <p class="stat-note">Avg {{ $stats['deploy']['avg_duration_ms'] > 0 ? number_format($stats['deploy']['avg_duration_ms'] / 1000, 1) . 's' : '—' }}</p>
        </div>
    </div>

    {{-- Traffic chart + Uptime --}}
    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <x-ui.card>
            <x-ui.card-header>
                <x-ui.card-title>Traffic — {{ $stats['mode'] === 'site' ? $stats['site_name'] : 'All sites' }}</x-ui.card-title>
                <div class="text-right">
                    <p class="text-lg font-semibold tabular-nums">{{ number_format($stats['total_visitors']) }} <span class="text-sm font-normal text-zinc-500">visitors</span></p>
                    <p class="text-xs text-zinc-500">Last {{ $days }} days</p>
                </div>
            </x-ui.card-header>

            @if (!empty($stats['daily']))
                @php
                    $maxVisitors = max(1, max(array_column($stats['daily'], 'visitors')));
                    $n = count($stats['daily']);
                    $vbW = 400; $vbH = 120; $pad = 8;
                    $plotW = $vbW - $pad * 2; $plotH = $vbH - $pad * 2;
                    $pts = [];
                    foreach ($stats['daily'] as $i => $day) {
                        $x = $n <= 1 ? $pad + $plotW / 2 : $pad + ($i / max(1, $n - 1)) * $plotW;
                        $y = $pad + $plotH - ($day['visitors'] / $maxVisitors) * $plotH;
                        $pts[] = round($x, 2).','.round($y, 2);
                    }
                    $lineD = 'M '.implode(' L ', $pts);
                    $firstX = (float) explode(',', $pts[0])[0];
                    $lastX  = (float) explode(',', $pts[$n - 1])[0];
                    $baseY  = $pad + $plotH;
                    $areaD  = $lineD.' L '.$lastX.' '.$baseY.' L '.$firstX.' '.$baseY.' Z';
                @endphp
                <div class="chart-shell">
                    <svg class="h-36 w-full" viewBox="0 0 {{ $vbW }} {{ $vbH }}" preserveAspectRatio="none">
                        @foreach ([0, 1, 2, 3] as $g)
                            <line x1="{{ $pad }}" y1="{{ $pad + ($g / 3) * $plotH }}" x2="{{ $vbW - $pad }}" y2="{{ $pad + ($g / 3) * $plotH }}" stroke="rgb(39 39 42)" stroke-width="1" vector-effect="non-scaling-stroke" />
                        @endforeach
                        <defs>
                            <linearGradient id="uniTrafficFill" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="rgb(52 211 153)" stop-opacity="0.35" />
                                <stop offset="100%" stop-color="rgb(52 211 153)" stop-opacity="0" />
                            </linearGradient>
                        </defs>
                        <path d="{{ $areaD }}" fill="url(#uniTrafficFill)" />
                        <path d="{{ $lineD }}" fill="none" stroke="rgb(52 211 153)" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke" />
                    </svg>
                    <div class="mt-2 flex justify-between text-[10px] text-zinc-500">
                        <span>{{ $stats['daily'][0]['date'] }}</span>
                        <span>{{ $stats['daily'][$n - 1]['date'] }}</span>
                    </div>
                </div>
            @else
                <x-ui.empty icon="chart-bar" title="No organic traffic data yet" description="Connect GA4 and run php artisan platform:sync-analytics." />
            @endif
        </x-ui.card>

        <div class="space-y-5">
            <x-ui.card>
                <x-ui.card-header>
                    <x-ui.card-title>{{ $stats['mode'] === 'site' ? $stats['site_name'] : 'Portfolio' }} — Uptime</x-ui.card-title>
                    @php
                        $upPct = $stats['uptime']['uptime_percent'];
                        $uptimeColor = $upPct >= 99.9 ? 'text-emerald-400' : ($upPct >= 99 ? 'text-amber-400' : 'text-red-400');
                    @endphp
                    <p class="text-lg font-semibold tabular-nums {{ $uptimeColor }}">{{ number_format($upPct, 1) }}%</p>
                </x-ui.card-header>

                <div class="flex h-14 items-end gap-px">
                    @foreach ($stats['uptime']['daily_bars'] ?? [] as $bar)
                        <div @class(['flex-1 min-w-0 rounded-sm min-h-[6px]', 'bg-emerald-500/85' => $bar['status'] === 'up', 'bg-amber-500/80' => $bar['status'] === 'degraded', 'bg-red-500/85' => $bar['status'] === 'down', 'bg-zinc-700/60' => $bar['status'] === 'unknown'])
                             title="{{ $bar['date'] }}: {{ ucfirst($bar['status']) }}"></div>
                    @endforeach
                </div>
                <div class="mt-2 flex flex-wrap gap-3 text-[10px] text-zinc-500">
                    <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-sm bg-emerald-500/85"></span>Up</span>
                    <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-sm bg-amber-500/80"></span>Degraded</span>
                    <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-sm bg-red-500/85"></span>Down</span>
                </div>
            </x-ui.card>

            <x-ui.card>
                <x-ui.card-header>
                    <x-ui.card-title>{{ $stats['mode'] === 'site' ? $stats['site_name'] : 'Portfolio' }} — Response time</x-ui.card-title>
                    <span class="font-mono text-xs text-zinc-500 tabular-nums">avg {{ number_format($stats['uptime']['avg_response_time']) }}ms &nbsp; p95 {{ number_format($stats['uptime']['p95_response_time']) }}ms</span>
                </x-ui.card-header>

                @if (!empty($stats['uptime']['response_series']))
                    @php
                        $series = $stats['uptime']['response_series'];
                        $msVals = array_column($series, 'ms');
                        $maxMs = max(1, ...$msVals);
                        $sw = 400; $sh = 100; $pL = 36; $pR = 8; $pT = 8; $pB = 12;
                        $pw = $sw - $pL - $pR; $ph = $sh - $pT - $pB;
                        $nc = count($series); $pathD = '';
                        foreach ($series as $i => $pt) {
                            $x = $pL + ($nc <= 1 ? $pw / 2 : ($i / max(1, $nc - 1)) * $pw);
                            $y = $pT + $ph - ($pt['ms'] / $maxMs) * $ph;
                            $pathD .= ($i === 0 ? 'M ' : ' L ').round($x, 2).' '.round($y, 2);
                        }
                        $yMid = (int) round($maxMs / 2);
                    @endphp
                    <svg class="h-28 w-full" viewBox="0 0 {{ $sw }} {{ $sh }}" preserveAspectRatio="none">
                        <line x1="{{ $pL }}" y1="{{ $pT }}" x2="{{ $sw - $pR }}" y2="{{ $pT }}" stroke="rgb(63 63 70)" stroke-width="1" stroke-dasharray="3 3" vector-effect="non-scaling-stroke" />
                        <line x1="{{ $pL }}" y1="{{ $pT + $ph / 2 }}" x2="{{ $sw - $pR }}" y2="{{ $pT + $ph / 2 }}" stroke="rgb(63 63 70)" stroke-width="1" stroke-dasharray="3 3" vector-effect="non-scaling-stroke" />
                        <line x1="{{ $pL }}" y1="{{ $pT + $ph }}" x2="{{ $sw - $pR }}" y2="{{ $pT + $ph }}" stroke="rgb(63 63 70)" stroke-width="1" stroke-dasharray="3 3" vector-effect="non-scaling-stroke" />
                        <text x="4" y="{{ $pT + 4 }}" class="chart-axis-label fill-zinc-500">{{ $maxMs }}ms</text>
                        <text x="4" y="{{ $pT + $ph / 2 + 3 }}" class="chart-axis-label fill-zinc-500">{{ $yMid }}ms</text>
                        <text x="4" y="{{ $pT + $ph + 3 }}" class="chart-axis-label fill-zinc-500">0ms</text>
                        <path d="{{ $pathD }}" fill="none" stroke="rgb(161 161 170)" stroke-width="1.5" vector-effect="non-scaling-stroke" stroke-linejoin="round" />
                        @foreach ($series as $i => $pt)
                            @php $x = $pL + ($nc <= 1 ? $pw / 2 : ($i / max(1, $nc - 1)) * $pw); $y = $pT + $ph - ($pt['ms'] / $maxMs) * $ph; @endphp
                            <circle cx="{{ $x }}" cy="{{ $y }}" r="{{ !empty($pt['spike']) ? 3.5 : 2 }}" fill="{{ !empty($pt['spike']) ? 'rgb(248 113 113)' : 'rgb(45 212 191)' }}" />
                        @endforeach
                    </svg>
                @else
                    <p class="py-8 text-center text-sm text-zinc-500">No response samples in this range yet.</p>
                @endif

                @if (!empty($stats['uptime']['recent']))
                    <div class="mt-3 border-t border-zinc-800/60 pt-3">
                        <p class="mb-2 text-xs text-zinc-500">Latest checks</p>
                        <div class="flex gap-px">
                            @foreach ($stats['uptime']['recent'] as $check)
                                <div @class(['h-4 flex-1 rounded-sm min-w-0', 'bg-emerald-500/55' => $check['is_up'] && empty($check['is_degraded']), 'bg-amber-500/55' => $check['is_up'] && !empty($check['is_degraded']), 'bg-red-500/65' => ! $check['is_up']])
                                     title="{{ $check['checked_at'] }} · {{ $check['is_up'] ? ($check['response_time_ms'] ?? '—') . 'ms' : 'Down' }}"></div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </x-ui.card>
        </div>
    </div>

    {{-- Top pages + Site health --}}
    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <x-ui.card>
            <x-ui.card-header>
                <x-ui.card-title>Top Pages</x-ui.card-title>
                <span class="text-xs text-zinc-500">Most viewed content</span>
            </x-ui.card-header>

            <div class="space-y-2">
                @forelse ($stats['top_pages'] as $page)
                    <div class="flex items-start justify-between gap-3 rounded-lg border border-zinc-800/70 px-3 py-2.5">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium">{{ $page['title'] }}</p>
                            <p class="font-mono text-xs text-zinc-500 truncate">{{ $page['url_path'] }}</p>
                            @if (!empty($page['site_name']) && $stats['mode'] === 'global')
                                <p class="mt-0.5 text-xs text-zinc-600">{{ $page['site_name'] }}</p>
                            @endif
                        </div>
                        <div class="shrink-0 text-right">
                            <p class="font-mono text-xs">{{ number_format($page['pageviews']) }} views</p>
                            <p class="font-mono text-xs text-zinc-500">{{ number_format($page['visitors']) }} users</p>
                        </div>
                    </div>
                @empty
                    <x-ui.empty icon="document-duplicate" title="No page analytics yet" />
                @endforelse
            </div>
        </x-ui.card>

        <x-ui.card>
            <x-ui.card-header>
                <x-ui.card-title>Site Health</x-ui.card-title>
                <x-ui.badge>{{ $stats['online_sites'] }} online</x-ui.badge>
            </x-ui.card-header>

            @if (!empty($stats['per_site']))
                <div class="max-h-96 space-y-2 overflow-y-auto pr-1">
                    @foreach ($stats['per_site'] as $siteStat)
                        <div class="flex items-center gap-3 rounded-lg border border-zinc-800/70 px-3 py-2.5">
                            <span @class(['h-2.5 w-2.5 rounded-full shrink-0', 'bg-emerald-400' => $siteStat['is_up'] === true, 'bg-red-400' => $siteStat['is_up'] === false, 'bg-zinc-400' => is_null($siteStat['is_up'])])></span>
                            <div class="min-w-0 flex-1">
                                <a href="{{ route('sites.show', $siteStat['id']) }}" class="truncate text-sm font-medium hover:text-emerald-400 transition-colors">{{ $siteStat['name'] }}</a>
                                <p class="text-xs text-zinc-500">{{ number_format($siteStat['pages_count']) }} pages &middot; {{ number_format($siteStat['visitors']) }} visitors</p>
                            </div>
                            <div class="shrink-0 text-right">
                                <p class="font-mono text-xs">{{ number_format($siteStat['pageviews']) }} views</p>
                                @if ($siteStat['last_check_at'])
                                    <p class="text-xs text-zinc-500">{{ $siteStat['last_check_at']->diffForHumans() }}</p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <x-ui.empty icon="globe-alt" title="No sites found" description="Create a site to start collecting analytics." />
            @endif
        </x-ui.card>
    </div>
</div>
