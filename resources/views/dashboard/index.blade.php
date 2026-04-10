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

        $totalSites = \App\Models\Site::count();
        $totalPages = \App\Models\Page::count();
        $seoIssueCount = \App\Models\Page::where(function ($q) {
            $q->whereNull('title')
              ->orWhereNull('meta_description')
              ->orWhere('seo_score', '<', 60);
        })->count();

        $latestChecks = \App\Models\UptimeCheck::query()
            ->select('site_id', \Illuminate\Support\Facades\DB::raw('MAX(checked_at) as last_check'))
            ->groupBy('site_id')
            ->get()
            ->pluck('last_check', 'site_id');

        $uptimePercent = 0;
        if ($latestChecks->isNotEmpty()) {
            $upCount = \App\Models\UptimeCheck::query()
                ->whereIn(\Illuminate\Support\Facades\DB::raw("CONCAT(site_id, '|', checked_at)"),
                    $latestChecks->map(fn ($date, $id) => "{$id}|{$date}")->values()
                )->where('is_up', true)->count();
            $uptimePercent = round(($upCount / max(1, $latestChecks->count())) * 100, 1);
        }

        $unreadMessages = \App\Models\FormSubmission::where('is_read', false)->where('is_spam', false)->count();
        $errorCount = \App\Models\Notification::where('is_read', false)
            ->whereIn('type', ['deploy_failed', 'uptime_down', 'ssl_expiring'])
            ->count();

        $activeSites = \App\Models\Site::query()
            ->with([
                'latestUptimeCheck:id,site_id,is_up,is_degraded,response_time_ms,checked_at',
                'pages:id,site_id,title,meta_description,seo_score',
            ])
            ->withCount('pages')
            ->orderBy('name')
            ->get();

        $trafficRows = collect();
        if ($activeSites->isNotEmpty()) {
            $trafficRows = \App\Models\AnalyticsSnapshot::query()
                ->join('pages', 'pages.id', '=', 'analytics_snapshots.page_id')
                ->whereIn('pages.site_id', $activeSites->pluck('id'))
                ->where('analytics_snapshots.date', '>=', now()->subDays(29)->toDateString())
                ->selectRaw('analytics_snapshots.date as day, SUM(analytics_snapshots.visitors) as visitors')
                ->groupBy('analytics_snapshots.date')
                ->orderBy('analytics_snapshots.date')
                ->pluck('visitors', 'day');
        }

        $trafficSeries = collect(range(29, 0))->map(function (int $daysAgo) use ($trafficRows) {
            $date = now()->subDays($daysAgo);
            $day = $date->toDateString();

            return [
                'day' => $day,
                'label' => $date->format('M j'),
                'visitors' => (int) ($trafficRows[$day] ?? 0),
            ];
        })->values();

        $maxTraffic = max(1, $trafficSeries->max('visitors'));
        $trafficVisitors = $trafficSeries->sum('visitors');

        $vbW = 820;
        $vbH = 220;
        $pad = 16;
        $plotW = $vbW - ($pad * 2);
        $plotH = $vbH - ($pad * 2);
        $pointCount = max(1, $trafficSeries->count() - 1);
        $chartPoints = [];

        foreach ($trafficSeries as $i => $point) {
            $x = $pad + (($i / $pointCount) * $plotW);
            $y = $pad + $plotH - (($point['visitors'] / $maxTraffic) * $plotH);
            $chartPoints[] = round($x, 2) . ',' . round($y, 2);
        }

        $lineD = 'M ' . implode(' L ', $chartPoints);
        $firstX = (float) explode(',', $chartPoints[0])[0];
        $lastX = (float) explode(',', $chartPoints[count($chartPoints) - 1])[0];
        $baseY = $pad + $plotH;
        $areaD = $lineD . ' L ' . $lastX . ' ' . $baseY . ' L ' . $firstX . ' ' . $baseY . ' Z';

        $siteInsights = $activeSites->take(2)->map(function (\App\Models\Site $site) {
            $checks = \App\Models\UptimeCheck::query()
                ->where('site_id', $site->id)
                ->where('checked_at', '>=', now()->subDays(30))
                ->orderBy('checked_at')
                ->get(['checked_at', 'is_up', 'is_degraded', 'response_time_ms']);

            $uptimePercent = $checks->isEmpty()
                ? 100.0
                : round(($checks->where('is_up', true)->count() / max(1, $checks->count())) * 100, 1);

            $dailyBars = collect(range(29, 0))->map(function (int $daysAgo) use ($checks) {
                $day = now()->subDays($daysAgo)->toDateString();
                $dayChecks = $checks->filter(fn ($check) => $check->checked_at->toDateString() === $day);

                if ($dayChecks->isEmpty()) {
                    return 'unknown';
                }

                if ($dayChecks->contains(fn ($check) => ! $check->is_up)) {
                    return 'down';
                }

                if ($dayChecks->contains(fn ($check) => (bool) $check->is_degraded)) {
                    return 'degraded';
                }

                return 'up';
            })->values();

            $responseSeries = $checks->pluck('response_time_ms')
                ->filter(fn ($ms) => $ms !== null)
                ->take(-24)
                ->map(fn ($ms) => (int) $ms)
                ->values();

            $avgResponse = $responseSeries->isEmpty() ? 0 : (int) round($responseSeries->avg());
            $p95Response = $responseSeries->isEmpty()
                ? 0
                : (int) $responseSeries->sort()->values()->get(max(0, (int) ceil($responseSeries->count() * 0.95) - 1));

            return [
                'site' => $site,
                'uptime_percent' => $uptimePercent,
                'daily_bars' => $dailyBars,
                'response_series' => $responseSeries,
                'avg_response' => $avgResponse,
                'p95_response' => $p95Response,
            ];
        });
    @endphp

    <div class="space-y-5 text-zinc-100">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <flux:heading size="xl" level="1" class="!text-zinc-100">{{ $greeting }}, {{ $user->name }}</flux:heading>
                <flux:text class="mt-1 !text-zinc-400">{{ now()->format('l, F j, Y') }}</flux:text>
            </div>
            <flux:button href="{{ route('sites.index') }}" variant="subtle" size="sm" class="!border-zinc-700 !bg-zinc-900/70 !text-zinc-200">View all sites</flux:button>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-6 gap-3">
            <div class="rounded-2xl border border-zinc-800/90 bg-zinc-900/85 px-4 py-3">
                <p class="text-[11px] font-medium text-zinc-500 uppercase tracking-[0.14em]">Sites</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums text-zinc-100">{{ $totalSites }}</p>
                <p class="mt-0.5 text-xs text-emerald-400">All online</p>
            </div>
            <div class="rounded-2xl border border-zinc-800/90 bg-zinc-900/85 px-4 py-3">
                <p class="text-[11px] font-medium text-zinc-500 uppercase tracking-[0.14em]">Uptime</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums text-zinc-100">{{ number_format($uptimePercent, 1) }}<span class="text-sm text-zinc-500">%</span></p>
            </div>
            <div class="rounded-2xl border border-zinc-800/90 bg-zinc-900/85 px-4 py-3">
                <p class="text-[11px] font-medium text-zinc-500 uppercase tracking-[0.14em]">Pages</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums text-zinc-100">{{ $totalPages }}</p>
            </div>
            <div class="rounded-2xl border border-zinc-800/90 bg-zinc-900/85 px-4 py-3">
                <p class="text-[11px] font-medium text-zinc-500 uppercase tracking-[0.14em]">Messages</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums text-zinc-100">{{ $unreadMessages }}</p>
                @if ($unreadMessages > 0)
                    <p class="mt-0.5 text-xs text-sky-400">Unread</p>
                @endif
            </div>
            <div class="rounded-2xl border border-zinc-800/90 bg-zinc-900/85 px-4 py-3">
                <p class="text-[11px] font-medium text-zinc-500 uppercase tracking-[0.14em]">Errors</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums {{ $errorCount > 0 ? 'text-red-400' : 'text-zinc-100' }}">{{ $errorCount }}</p>
                @if ($errorCount > 0)
                    <p class="mt-0.5 text-xs text-red-400">Needs attention</p>
                @endif
            </div>
            <div class="rounded-2xl border border-zinc-800/90 bg-zinc-900/85 px-4 py-3">
                <p class="text-[11px] font-medium text-zinc-500 uppercase tracking-[0.14em]">SEO Issues</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums {{ $seoIssueCount > 0 ? 'text-amber-400' : 'text-zinc-100' }}">{{ $seoIssueCount }}</p>
                @if ($seoIssueCount > 0)
                    <p class="mt-0.5 text-xs text-amber-400">Needs attention</p>
                @endif
            </div>
        </div>

        <section class="rounded-2xl border border-zinc-800/90 bg-zinc-900/85 p-4 md:p-5">
            <div class="mb-3 flex items-start justify-between gap-4">
                <div>
                    <p class="text-sm text-zinc-400">Traffic — All sites</p>
                    <p class="text-[11px] text-zinc-500">Last 30 days</p>
                </div>
                <div class="text-right">
                    <p class="text-xl font-semibold tabular-nums text-zinc-100">{{ number_format($trafficVisitors) }} <span class="text-sm font-normal text-zinc-500">visitors</span></p>
                    <p class="text-[11px] text-zinc-500">{{ $trafficSeries->first()['label'] }} - {{ $trafficSeries->last()['label'] }}</p>
                </div>
            </div>

            <div class="rounded-xl border border-zinc-800 bg-zinc-950/80 p-3">
                <svg class="h-64 w-full" viewBox="0 0 {{ $vbW }} {{ $vbH }}" preserveAspectRatio="none" role="img" aria-label="Traffic trend for all sites">
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

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            @livewire('dashboard.alerts-panel')
            @livewire('dashboard.activity-feed')
        </div>

        @livewire('dashboard.site-health-table')

        @if ($siteInsights->isNotEmpty())
            <div class="grid grid-cols-1 xl:grid-cols-2 gap-5">
                @foreach ($siteInsights as $insight)
                    <div class="space-y-4">
                        <section class="rounded-2xl border border-zinc-800/90 bg-zinc-900/85 p-4">
                            <div class="mb-3 flex items-start justify-between gap-3">
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

                        <section class="rounded-2xl border border-zinc-800/90 bg-zinc-900/85 p-4">
                            <div class="mb-3 flex items-start justify-between gap-3">
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
                            <div class="rounded-xl border border-zinc-800 bg-zinc-950/80 p-2">
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

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            @livewire('dashboard.seo-issues-panel')
            @livewire('dashboard.upcoming-panel')
            @livewire('dashboard.expenses-panel')
        </div>
    </div>
</x-layouts.app>
