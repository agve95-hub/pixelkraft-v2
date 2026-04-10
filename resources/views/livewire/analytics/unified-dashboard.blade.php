<div class="space-y-6" wire:poll.30s>
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">Analytics & Performance</flux:heading>
            <flux:text class="mt-1">
                {{ $stats['mode'] === 'site' ? 'Viewing ' . $stats['site_name'] : 'Portfolio-wide traffic, uptime, and deployment health' }}
            </flux:text>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <select wire:model.live="siteId" class="flux-input text-sm w-52">
                <option value="">All Sites</option>
                @foreach ($sites as $site)
                    <option value="{{ $site->id }}">{{ $site->name }}</option>
                @endforeach
            </select>

            <select wire:model.live="days" class="flux-input text-sm w-40">
                <option value="7">Last 7 days</option>
                <option value="30">Last 30 days</option>
                <option value="90">Last 90 days</option>
            </select>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
        <flux:card>
            <flux:text size="xs" class="uppercase tracking-wide">Visitors</flux:text>
            <flux:heading size="xl" class="mt-2 font-mono">{{ number_format($stats['total_visitors']) }}</flux:heading>
            <flux:text size="xs" class="mt-1 text-zinc-500">{{ $stats['traffic_label'] ?? 'Traffic' }} · selected period</flux:text>
        </flux:card>

        <flux:card>
            <flux:text size="xs" class="uppercase tracking-wide">Pageviews</flux:text>
            <flux:heading size="xl" class="mt-2 font-mono">{{ number_format($stats['total_pageviews']) }}</flux:heading>
            <flux:text size="xs" class="mt-1">Total page impressions</flux:text>
        </flux:card>

        <flux:card>
            <flux:text size="xs" class="uppercase tracking-wide">Online Users Today</flux:text>
            <flux:heading size="xl" class="mt-2 font-mono">{{ number_format($stats['users_today']) }}</flux:heading>
            <flux:text size="xs" class="mt-1">Visitors recorded today</flux:text>
        </flux:card>

        <flux:card>
            <flux:text size="xs" class="uppercase tracking-wide">Runtime</flux:text>
            <div class="mt-2 flex items-baseline gap-2">
                <flux:heading size="xl" class="font-mono">{{ number_format($stats['runtime']['runtime_percent'], 2) }}%</flux:heading>
                <flux:badge size="sm" :color="$stats['runtime']['runtime_percent'] >= 99.5 ? 'lime' : ($stats['runtime']['runtime_percent'] >= 97 ? 'yellow' : 'red')">
                    {{ $stats['runtime']['runtime_percent'] >= 99.5 ? 'Excellent' : 'Needs attention' }}
                </flux:badge>
            </div>
            <flux:text size="xs" class="mt-1">Calculated from uptime checks</flux:text>
        </flux:card>

        <flux:card>
            <flux:text size="xs" class="uppercase tracking-wide">Downtime</flux:text>
            <flux:heading size="xl" class="mt-2 font-mono">{{ number_format($stats['runtime']['downtime_minutes']) }}m</flux:heading>
            <flux:text size="xs" class="mt-1">{{ number_format($stats['runtime']['downtime_hours'], 1) }}h total in selected period</flux:text>
        </flux:card>

        <flux:card>
            <flux:text size="xs" class="uppercase tracking-wide">Deploy Success</flux:text>
            <div class="mt-2 flex items-baseline gap-2">
                <flux:heading size="xl" class="font-mono">{{ number_format($stats['deploy']['success_rate'], 1) }}%</flux:heading>
                <flux:text size="xs">({{ $stats['deploy']['successful'] }}/{{ $stats['deploy']['total'] }})</flux:text>
            </div>
            <flux:text size="xs" class="mt-1">Average deploy: {{ $stats['deploy']['avg_duration_ms'] > 0 ? number_format($stats['deploy']['avg_duration_ms'] / 1000, 1) . 's' : '—' }}</flux:text>
        </flux:card>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        <flux:card class="rounded-2xl border-zinc-800 bg-zinc-900/40 dark:bg-zinc-900/60">
            <div class="flex items-start justify-between gap-4 mb-1">
                <div class="flex items-center gap-2 text-zinc-400">
                    <svg class="h-4 w-4 shrink-0 text-emerald-400/90" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                    </svg>
                    <flux:heading size="sm" class="!text-zinc-300 font-normal">
                        Traffic — {{ $stats['mode'] === 'site' ? $stats['site_name'] : 'All sites' }}
                    </flux:heading>
                </div>
                <div class="text-right">
                    <p class="text-lg font-semibold tabular-nums text-zinc-100">{{ number_format($stats['total_visitors']) }} <span class="text-sm font-normal text-zinc-400">visitors</span></p>
                    <flux:text size="xs" class="text-zinc-500">Last {{ $days }} days · {{ $stats['traffic_label'] ?? 'GA / CDN' }}</flux:text>
                </div>
            </div>

            @if (!empty($stats['daily']))
                @php
                    $maxVisitors = max(1, max(array_column($stats['daily'], 'visitors')));
                    $n = count($stats['daily']);
                    $vbW = 400;
                    $vbH = 120;
                    $pad = 8;
                    $plotW = $vbW - $pad * 2;
                    $plotH = $vbH - $pad * 2;
                    $pts = [];
                    $area = [];
                    foreach ($stats['daily'] as $i => $day) {
                        $x = $n <= 1 ? $pad + $plotW / 2 : $pad + ($i / max(1, $n - 1)) * $plotW;
                        $y = $pad + $plotH - ($day['visitors'] / $maxVisitors) * $plotH;
                        $pts[] = round($x, 2).','.round($y, 2);
                    }
                    $lineD = 'M '.implode(' L ', $pts);
                    $firstX = (float) explode(',', $pts[0] ?? ($pad.','.($pad + $plotH)))[0];
                    $lastX = (float) explode(',', $pts[$n - 1] ?? ($pad + $plotW).','.($pad + $plotH))[0];
                    $baseY = $pad + $plotH;
                    $areaD = $lineD.' L '.$lastX.' '.$baseY.' L '.$firstX.' '.$baseY.' Z';
                    $yTicks = [
                        0,
                        (int) round($maxVisitors / 3),
                        (int) round(2 * $maxVisitors / 3),
                        $maxVisitors,
                    ];
                @endphp
                <div class="relative mt-4 rounded-xl bg-zinc-950/80 px-2 pt-6 pb-2 ring-1 ring-zinc-800/80">
                    <div class="absolute left-2 top-2 bottom-8 w-8 flex flex-col justify-between text-[10px] tabular-nums text-zinc-500 pr-1 text-right">
                        @foreach (array_reverse($yTicks) as $tick)
                            <span>{{ $tick }}</span>
                        @endforeach
                    </div>
                    <svg class="ml-9 h-36 w-full" viewBox="0 0 {{ $vbW }} {{ $vbH }}" preserveAspectRatio="none" role="img" aria-label="Organic traffic trend">
                        @foreach ([0, 1, 2, 3] as $g)
                            <line x1="{{ $pad }}" y1="{{ $pad + ($g / 3) * $plotH }}" x2="{{ $vbW - $pad }}" y2="{{ $pad + ($g / 3) * $plotH }}" stroke="rgb(39 39 42)" stroke-width="1" vector-effect="non-scaling-stroke" />
                        @endforeach
                        <defs>
                            <linearGradient id="trafficFill" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="rgb(52 211 153)" stop-opacity="0.35" />
                                <stop offset="100%" stop-color="rgb(52 211 153)" stop-opacity="0" />
                            </linearGradient>
                        </defs>
                        <path d="{{ $areaD }}" fill="url(#trafficFill)" />
                        <path d="{{ $lineD }}" fill="none" stroke="rgb(52 211 153)" stroke-width="2" vector-effect="non-scaling-stroke" stroke-linejoin="round" stroke-linecap="round" />
                    </svg>
                </div>
                <div class="mt-2 flex justify-between text-[10px] text-zinc-500">
                    <span>{{ $stats['daily'][0]['date'] }}</span>
                    <span>{{ $stats['daily'][$n - 1]['date'] }}</span>
                </div>
            @else
                <div class="py-12 text-center rounded-xl bg-zinc-950/50 mt-4 ring-1 ring-zinc-800/60">
                    <flux:subheading>No organic traffic data yet</flux:subheading>
                    <flux:text size="sm" class="mt-1 max-w-md mx-auto text-zinc-500">
                        Set a GA4 property on each site, add the service account to the property, place credentials at <code class="text-zinc-400">GOOGLE_ANALYTICS_CREDENTIALS_PATH</code>, then run <code class="text-zinc-400">php artisan pixelkraft:sync-analytics</code>.
                    </flux:text>
                </div>
            @endif
        </flux:card>

        <div class="space-y-6">
            <flux:card class="rounded-2xl border-zinc-800 bg-zinc-900/40 dark:bg-zinc-900/60">
                <div class="flex items-start justify-between gap-3 mb-3">
                    <flux:heading size="sm" class="!text-zinc-300 font-normal">
                        {{ $stats['mode'] === 'site' ? $stats['site_name'] : 'Portfolio' }} — Uptime
                    </flux:heading>
                    <div class="text-right">
                        @php
                            $upPct = $stats['uptime']['uptime_percent'];
                            $uptimeColor = $upPct >= 99.9 ? 'text-emerald-400' : ($upPct >= 99 ? 'text-amber-400' : 'text-red-400');
                        @endphp
                        <p class="text-lg font-semibold tabular-nums {{ $uptimeColor }}">{{ number_format($upPct, 1) }}%</p>
                        <flux:text size="xs" class="text-zinc-500">Last {{ $days }} days</flux:text>
                    </div>
                </div>

                <div class="flex h-14 items-end gap-0.5 sm:gap-px">
                    @foreach ($stats['uptime']['daily_bars'] ?? [] as $bar)
                        <div
                            @class([
                                'flex-1 min-w-0 rounded-sm min-h-[6px] transition',
                                'bg-emerald-500/85' => $bar['status'] === 'up',
                                'bg-amber-500/80' => $bar['status'] === 'degraded',
                                'bg-red-500/85' => $bar['status'] === 'down',
                                'bg-zinc-700/60' => $bar['status'] === 'unknown',
                            ])
                            title="{{ $bar['date'] }}: {{ ucfirst($bar['status']) }}"
                        ></div>
                    @endforeach
                </div>

                <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-[10px] text-zinc-500">
                    <div class="flex flex-wrap gap-3">
                        <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-sm bg-emerald-500/85"></span> Up</span>
                        <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-sm bg-amber-500/80"></span> Degraded</span>
                        <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-sm bg-red-500/85"></span> Down</span>
                    </div>
                    <span>From scheduled checks · slow = degraded</span>
                </div>
            </flux:card>

            <flux:card class="rounded-2xl border-zinc-800 bg-zinc-900/40 dark:bg-zinc-900/60">
                <div class="flex items-start justify-between gap-3 mb-3">
                    <flux:heading size="sm" class="!text-zinc-300 font-normal">
                        {{ $stats['mode'] === 'site' ? $stats['site_name'] : 'Portfolio' }} — Response time
                    </flux:heading>
                    <flux:text size="xs" class="text-zinc-500 tabular-nums">
                        avg {{ number_format($stats['uptime']['avg_response_time']) }}ms
                        &nbsp; p95 {{ number_format($stats['uptime']['p95_response_time']) }}ms
                    </flux:text>
                </div>

                @if (!empty($stats['uptime']['response_series']))
                    @php
                        $series = $stats['uptime']['response_series'];
                        $msVals = array_column($series, 'ms');
                        $maxMs = max(1, ...$msVals);
                        $sw = 400;
                        $sh = 100;
                        $pL = 36;
                        $pR = 8;
                        $pT = 8;
                        $pB = 12;
                        $pw = $sw - $pL - $pR;
                        $ph = $sh - $pT - $pB;
                        $nc = count($series);
                        $pathD = '';
                        foreach ($series as $i => $pt) {
                            $x = $pL + ($nc <= 1 ? $pw / 2 : ($i / max(1, $nc - 1)) * $pw);
                            $y = $pT + $ph - ($pt['ms'] / $maxMs) * $ph;
                            $pathD .= ($i === 0 ? 'M ' : ' L ').round($x, 2).' '.round($y, 2);
                        }
                        $yMid = (int) round($maxMs / 2);
                    @endphp
                    <svg class="w-full h-28" viewBox="0 0 {{ $sw }} {{ $sh }}" preserveAspectRatio="none" role="img" aria-label="Response time trend">
                        <line x1="{{ $pL }}" y1="{{ $pT }}" x2="{{ $sw - $pR }}" y2="{{ $pT }}" stroke="rgb(63 63 70)" stroke-width="1" stroke-dasharray="3 3" vector-effect="non-scaling-stroke" />
                        <line x1="{{ $pL }}" y1="{{ $pT + $ph / 2 }}" x2="{{ $sw - $pR }}" y2="{{ $pT + $ph / 2 }}" stroke="rgb(63 63 70)" stroke-width="1" stroke-dasharray="3 3" vector-effect="non-scaling-stroke" />
                        <line x1="{{ $pL }}" y1="{{ $pT + $ph }}" x2="{{ $sw - $pR }}" y2="{{ $pT + $ph }}" stroke="rgb(63 63 70)" stroke-width="1" stroke-dasharray="3 3" vector-effect="non-scaling-stroke" />
                        <text x="4" y="{{ $pT + 4 }}" class="fill-zinc-500" style="font-size: 9px">{{ $maxMs }}ms</text>
                        <text x="4" y="{{ $pT + $ph / 2 + 3 }}" class="fill-zinc-500" style="font-size: 9px">{{ $yMid }}ms</text>
                        <text x="4" y="{{ $pT + $ph + 3 }}" class="fill-zinc-500" style="font-size: 9px">0ms</text>
                        <path d="{{ $pathD }}" fill="none" stroke="rgb(161 161 170)" stroke-width="1.5" vector-effect="non-scaling-stroke" stroke-linejoin="round" />
                        @foreach ($series as $i => $pt)
                            @php
                                $x = $pL + ($nc <= 1 ? $pw / 2 : ($i / max(1, $nc - 1)) * $pw);
                                $y = $pT + $ph - ($pt['ms'] / $maxMs) * $ph;
                                $r = !empty($pt['spike']) ? 3.5 : 2;
                            @endphp
                            <circle cx="{{ $x }}" cy="{{ $y }}" r="{{ $r }}" fill="{{ !empty($pt['spike']) ? 'rgb(248 113 113)' : 'rgb(45 212 191)' }}" />
                        @endforeach
                    </svg>
                @else
                    <div class="py-8 text-center text-sm text-zinc-500">No response samples in this range yet.</div>
                @endif

                @if (!empty($stats['uptime']['recent']))
                    <div class="mt-4 pt-3 border-t border-zinc-800">
                        <flux:text size="xs" class="mb-2 text-zinc-500">Latest checks</flux:text>
                        <div class="flex gap-0.5">
                            @foreach ($stats['uptime']['recent'] as $check)
                                <div
                                    @class([
                                        'h-4 flex-1 rounded-sm min-w-0',
                                        'bg-emerald-500/55' => $check['is_up'] && empty($check['is_degraded']),
                                        'bg-amber-500/55' => $check['is_up'] && !empty($check['is_degraded']),
                                        'bg-red-500/65' => ! $check['is_up'],
                                    ])
                                    title="{{ $check['checked_at'] }} · {{ $check['is_up'] ? ($check['response_time_ms'] ?? '—') . 'ms' : 'Down' }}"
                                ></div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </flux:card>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        <flux:card>
            <div class="flex items-center justify-between mb-4">
                <flux:heading size="sm">Top Pages</flux:heading>
                <flux:text size="xs">Most viewed content</flux:text>
            </div>

            <div class="space-y-2">
                @forelse ($stats['top_pages'] as $page)
                    <div class="flex items-start justify-between gap-3 rounded-lg border border-zinc-200 dark:border-zinc-700 px-3 py-2.5">
                        <div class="min-w-0">
                            <flux:text size="sm" class="font-medium truncate">{{ $page['title'] }}</flux:text>
                            <flux:text size="xs" class="font-mono truncate">{{ $page['url_path'] }}</flux:text>
                            @if (!empty($page['site_name']) && $stats['mode'] === 'global')
                                <flux:text size="xs" class="mt-0.5">Site: {{ $page['site_name'] }}</flux:text>
                            @endif
                        </div>
                        <div class="text-right shrink-0">
                            <flux:text size="xs" class="font-mono">{{ number_format($page['pageviews']) }} views</flux:text>
                            <flux:text size="xs" class="font-mono">{{ number_format($page['visitors']) }} users</flux:text>
                        </div>
                    </div>
                @empty
                    <div class="py-8 text-center">
                        <flux:subheading>No page analytics yet</flux:subheading>
                    </div>
                @endforelse
            </div>
        </flux:card>

        <flux:card>
            <div class="flex items-center justify-between mb-4">
                <flux:heading size="sm">Site Health</flux:heading>
                <flux:badge size="sm" color="zinc">{{ $stats['online_sites'] }} online</flux:badge>
            </div>

            @if (!empty($stats['per_site']))
                <div class="space-y-2 max-h-96 overflow-y-auto pr-1">
                    @foreach ($stats['per_site'] as $siteStat)
                        <div class="flex items-center gap-3 rounded-lg border border-zinc-200 dark:border-zinc-700 px-3 py-2.5">
                            <span @class([
                                'h-2.5 w-2.5 rounded-full shrink-0',
                                'bg-lime-500' => $siteStat['is_up'] === true,
                                'bg-red-500' => $siteStat['is_up'] === false,
                                'bg-zinc-400' => is_null($siteStat['is_up']),
                            ])></span>

                            <div class="min-w-0 flex-1">
                                <flux:link href="{{ route('sites.show', $siteStat['id']) }}" class="font-medium text-sm truncate">
                                    {{ $siteStat['name'] }}
                                </flux:link>
                                <flux:text size="xs">
                                    {{ number_format($siteStat['pages_count']) }} pages · {{ number_format($siteStat['visitors']) }} visitors
                                </flux:text>
                            </div>

                            <div class="text-right shrink-0">
                                <flux:text size="xs" class="font-mono">{{ number_format($siteStat['pageviews']) }} views</flux:text>
                                @if ($siteStat['last_check_at'])
                                    <flux:text size="xs">{{ $siteStat['last_check_at']->diffForHumans() }}</flux:text>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="py-8 text-center">
                    <flux:subheading>No sites found</flux:subheading>
                    <flux:text size="sm" class="mt-1">Create a site to start collecting analytics.</flux:text>
                </div>
            @endif
        </flux:card>
    </div>
</div>
