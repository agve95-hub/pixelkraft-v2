<div class="space-y-6">
    {{-- Controls --}}
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <select wire:model.live="siteId" class="flux-input text-sm w-auto">
                <option value="">All Sites</option>
                @foreach ($sites as $site)
                    <option value="{{ $site->id }}">{{ $site->name }}</option>
                @endforeach
            </select>

            <select wire:model.live="days" class="flux-input text-sm w-auto">
                <option value="7">Last 7 days</option>
                <option value="30">Last 30 days</option>
                <option value="90">Last 90 days</option>
            </select>
        </div>
    </div>

    {{-- Summary Stats --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="card">
            <p class="text-[10px] uppercase tracking-wider text-zinc-600">Visitors</p>
            <p class="text-2xl font-bold text-zinc-100 mt-1 mono">{{ number_format($stats['total_visitors']) }}</p>
        </div>
        <div class="card">
            <p class="text-[10px] uppercase tracking-wider text-zinc-600">Pageviews</p>
            <p class="text-2xl font-bold text-zinc-100 mt-1 mono">{{ number_format($stats['total_pageviews']) }}</p>
        </div>
        @if (isset($stats['avg_bounce_rate']))
            <div class="card">
                <p class="text-[10px] uppercase tracking-wider text-zinc-600">Bounce Rate</p>
                <p class="text-2xl font-bold text-zinc-100 mt-1 mono">{{ $stats['avg_bounce_rate'] }}%</p>
            </div>
        @endif
        @if ($uptime)
            <div class="card">
                <p class="text-[10px] uppercase tracking-wider text-zinc-600">Uptime</p>
                <p @class([
                    'text-2xl font-bold mt-1 mono',
                    'text-emerald-400' => $uptime['uptime_percent'] >= 99.5,
                    'text-amber-400'   => $uptime['uptime_percent'] >= 95 && $uptime['uptime_percent'] < 99.5,
                    'text-red-400'     => $uptime['uptime_percent'] < 95,
                ])>{{ $uptime['uptime_percent'] }}%</p>
            </div>
        @endif
    </div>

    {{-- Uptime Details --}}
    @if ($uptime)
        <div class="card">
            <h3 class="text-sm font-semibold text-zinc-200 mb-4">Uptime Monitor</h3>
            <div class="grid grid-cols-3 gap-4 mb-4">
                <div>
                    <p class="text-[10px] uppercase tracking-wider text-zinc-600">Avg Response</p>
                    <p class="text-sm font-semibold text-zinc-100 mt-1 mono">{{ $uptime['avg_response_time'] }}ms</p>
                </div>
                <div>
                    <p class="text-[10px] uppercase tracking-wider text-zinc-600">Total Checks</p>
                    <p class="text-sm font-semibold text-zinc-100 mt-1 mono">{{ number_format($uptime['total_checks']) }}</p>
                </div>
                <div>
                    <p class="text-[10px] uppercase tracking-wider text-zinc-600">Downtime Events</p>
                    <p @class([
                        'text-sm font-semibold mt-1 mono',
                        'text-emerald-400' => $uptime['downtime_events'] === 0,
                        'text-red-400'     => $uptime['downtime_events'] > 0,
                    ])>{{ $uptime['downtime_events'] }}</p>
                </div>
            </div>

            {{-- Uptime bar visualization --}}
            @if (!empty($uptime['recent']))
                <div class="flex gap-0.5">
                    @foreach ($uptime['recent'] as $check)
                        <div
                            @class([
                                'flex-1 h-6 rounded-sm',
                                'bg-emerald-500/40' => $check['is_up'],
                                'bg-red-500/60'     => !$check['is_up'],
                            ])
                            title="{{ $check['checked_at'] }}: {{ $check['is_up'] ? $check['response_time_ms'] . 'ms' : 'DOWN' }}"
                        ></div>
                    @endforeach
                </div>
                <p class="text-[10px] text-zinc-600 mt-1">Each bar = one uptime check. Green = up, Red = down.</p>
            @endif
        </div>
    @endif

    {{-- Per-site breakdown (global view) --}}
    @if (!$siteId && !empty($stats['per_site']))
        <div class="card">
            <h3 class="text-sm font-semibold text-zinc-200 mb-4">Sites by Traffic</h3>
            <div class="space-y-2">
                @foreach ($stats['per_site'] as $siteStat)
                    <div class="flex items-center gap-3 rounded-lg px-3 py-2 hover:bg-zinc-800/30 transition">
                        {{-- Uptime dot --}}
                        @if ($siteStat['is_up'] !== null)
                            <span @class([
                                'h-2 w-2 rounded-full flex-shrink-0',
                                'bg-emerald-400' => $siteStat['is_up'],
                                'bg-red-400'     => !$siteStat['is_up'],
                            ])></span>
                        @else
                            <span class="h-2 w-2 rounded-full bg-zinc-600 flex-shrink-0"></span>
                        @endif

                        <div class="flex-1 min-w-0">
                            <a href="{{ route('dashboard') }}?site={{ $siteStat['id'] }}" class="text-sm font-medium text-zinc-200 hover:text-violet-400 transition">
                                {{ $siteStat['name'] }}
                            </a>
                        </div>

                        <div class="flex items-center gap-6 flex-shrink-0">
                            <div class="text-right">
                                <p class="mono text-xs text-zinc-300">{{ number_format($siteStat['visitors']) }}</p>
                                <p class="text-[10px] text-zinc-600">visitors</p>
                            </div>
                            <div class="text-right">
                                <p class="mono text-xs text-zinc-300">{{ number_format($siteStat['pageviews']) }}</p>
                                <p class="text-[10px] text-zinc-600">pageviews</p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Top Pages (single site view) --}}
    @if ($siteId && !empty($stats['top_pages']))
        <div class="card">
            <h3 class="text-sm font-semibold text-zinc-200 mb-4">Top Pages</h3>
            <div class="space-y-1">
                @foreach ($stats['top_pages'] as $pageStat)
                    @php $pageModel = \App\Models\Page::find($pageStat['page_id']); @endphp
                    @if ($pageModel)
                        <div class="flex items-center justify-between rounded-lg px-3 py-2 hover:bg-zinc-800/30 transition">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm text-zinc-200 truncate">{{ $pageModel->title ?? $pageModel->url_path }}</p>
                                <p class="mono text-[10px] text-zinc-600">{{ $pageModel->url_path }}</p>
                            </div>
                            <div class="flex items-center gap-4 flex-shrink-0">
                                <span class="mono text-xs text-zinc-300">{{ number_format($pageStat['pageviews']) }} views</span>
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    @endif

    {{-- Daily Chart placeholder --}}
    @if (!empty($stats['daily']))
        <div class="card">
            <h3 class="text-sm font-semibold text-zinc-200 mb-4">Daily Traffic</h3>
            <div class="flex items-end gap-1 h-32">
                @php
                    $maxVisitors = max(1, max(array_column($stats['daily'], 'visitors')));
                @endphp
                @foreach ($stats['daily'] as $day)
                    <div
                        class="flex-1 bg-violet-500/30 rounded-t hover:bg-violet-500/50 transition relative group"
                        style="height: {{ ($day['visitors'] / $maxVisitors) * 100 }}%"
                        title="{{ $day['date'] }}: {{ $day['visitors'] }} visitors"
                    >
                        <div class="absolute -top-6 left-1/2 -translate-x-1/2 hidden group-hover:block mono text-[10px] text-zinc-400 whitespace-nowrap">
                            {{ $day['visitors'] }}
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
