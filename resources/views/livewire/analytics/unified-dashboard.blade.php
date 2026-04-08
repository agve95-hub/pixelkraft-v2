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
            <flux:text size="xs" class="mt-1">Unique visitors in selected period</flux:text>
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

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <flux:card class="xl:col-span-2">
            <div class="flex items-center justify-between mb-4">
                <flux:heading size="sm">Traffic Trend</flux:heading>
                <flux:text size="xs">Visitors per day</flux:text>
            </div>

            @if (!empty($stats['daily']))
                @php $maxVisitors = max(1, max(array_column($stats['daily'], 'visitors'))); @endphp
                <div class="h-52 flex items-end gap-1.5">
                    @foreach ($stats['daily'] as $day)
                        <div
                            class="group relative flex-1 rounded-t-md bg-violet-500/35 hover:bg-violet-500/55 transition"
                            style="height: {{ max(6, ($day['visitors'] / $maxVisitors) * 100) }}%"
                            title="{{ $day['date'] }}: {{ number_format($day['visitors']) }} visitors"
                        >
                            <div class="absolute -top-7 left-1/2 -translate-x-1/2 hidden group-hover:block rounded-md bg-zinc-900 px-1.5 py-0.5 text-[10px] text-white whitespace-nowrap">
                                {{ number_format($day['visitors']) }}
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-3 flex justify-between text-[10px] text-zinc-500">
                    <span>{{ $stats['daily'][0]['date'] }}</span>
                    <span>{{ $stats['daily'][count($stats['daily']) - 1]['date'] }}</span>
                </div>
            @else
                <div class="py-12 text-center">
                    <flux:subheading>No traffic snapshots yet</flux:subheading>
                    <flux:text size="sm" class="mt-1">Traffic charts will appear after analytics sync runs.</flux:text>
                </div>
            @endif
        </flux:card>

        <flux:card>
            <flux:heading size="sm" class="mb-4">Uptime & Speed</flux:heading>

            <div class="space-y-3">
                <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 px-3 py-2">
                    <flux:text size="xs" class="uppercase tracking-wide">Uptime</flux:text>
                    <flux:heading size="lg" class="mt-1 font-mono">{{ number_format($stats['uptime']['uptime_percent'], 2) }}%</flux:heading>
                </div>
                <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 px-3 py-2">
                    <flux:text size="xs" class="uppercase tracking-wide">Avg Response</flux:text>
                    <flux:heading size="lg" class="mt-1 font-mono">{{ number_format($stats['uptime']['avg_response_time']) }}ms</flux:heading>
                </div>
                <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 px-3 py-2">
                    <flux:text size="xs" class="uppercase tracking-wide">P95 Response</flux:text>
                    <flux:heading size="lg" class="mt-1 font-mono">{{ number_format($stats['uptime']['p95_response_time']) }}ms</flux:heading>
                </div>
                <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 px-3 py-2">
                    <flux:text size="xs" class="uppercase tracking-wide">Downtime Events</flux:text>
                    <flux:heading size="lg" class="mt-1 font-mono">{{ number_format($stats['uptime']['downtime_events']) }}</flux:heading>
                </div>
            </div>

            @if (!empty($stats['uptime']['recent']))
                <div class="mt-4">
                    <flux:text size="xs" class="mb-2">Latest checks</flux:text>
                    <div class="flex gap-0.5">
                        @foreach ($stats['uptime']['recent'] as $check)
                            <div
                                @class([
                                    'h-5 flex-1 rounded-sm',
                                    'bg-lime-500/60' => $check['is_up'],
                                    'bg-red-500/70' => ! $check['is_up'],
                                ])
                                title="{{ $check['checked_at'] }} · {{ $check['is_up'] ? ($check['response_time_ms'] ?? '—') . 'ms' : 'Down' }}"
                            ></div>
                        @endforeach
                    </div>
                </div>
            @endif
        </flux:card>
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
