<?php

namespace App\Livewire\Analytics;

use App\Models\AnalyticsSnapshot;
use App\Models\DeployLog;
use App\Models\Page;
use App\Models\Site;
use App\Models\UptimeCheck;
use App\Services\AnalyticsAggregator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Component;

class UnifiedDashboard extends Component
{
    public ?string $siteId = null;
    public int $days = 30;

    public function render(): View
    {
        $aggregator = app(AnalyticsAggregator::class);
        $activeSites = Site::query()
            ->where('is_active', true)
            ->with('latestUptimeCheck')
            ->withCount('pages')
            ->orderBy('name')
            ->get();

        if ($this->siteId) {
            $site = Site::with(['pages', 'latestUptimeCheck'])->findOrFail($this->siteId);
            $analytics = $aggregator->getSiteStats($site, $this->days);
            $uptime = $this->getUptimeStats($site);
            $deploy = $this->getDeployStats($site->id);
            $siteVisitors = (int) $analytics['total_visitors'];
            $sitePageviews = (int) $analytics['total_pageviews'];

            $stats = [
                'mode' => 'site',
                'site_name' => $site->name,
                'total_visitors' => $siteVisitors,
                'total_pageviews' => $sitePageviews,
                'users_today' => $this->getUsersToday(collect([$site->id])),
                'daily' => $analytics['daily'],
                'top_pages' => $this->hydrateTopPages($analytics['top_pages']),
                'per_site' => [[
                    'id' => $site->id,
                    'name' => $site->name,
                    'slug' => $site->slug,
                    'pages_count' => $site->pages->count(),
                    'visitors' => $siteVisitors,
                    'pageviews' => $sitePageviews,
                    'is_up' => $site->latestUptimeCheck?->is_up,
                    'last_check_at' => $site->latestUptimeCheck?->checked_at,
                ]],
                'online_sites' => $this->countOnlineSites($activeSites),
                'uptime' => $uptime,
                'deploy' => $deploy,
                'runtime' => $this->getRuntimeStats($uptime['uptime_percent']),
            ];

            $sites = $activeSites->contains('id', $site->id)
                ? $activeSites
                : $activeSites->push($site);
        } else {
            $sites = $activeSites;
            $stats = $this->getGlobalStats($sites);
        }

        return view('livewire.analytics.unified-dashboard', [
            'sites' => $sites,
            'stats' => $stats,
        ]);
    }

    private function getGlobalStats(Collection $sites): array
    {
        if ($sites->isEmpty()) {
            return [
                'mode' => 'global',
                'site_name' => null,
                'total_visitors' => 0,
                'total_pageviews' => 0,
                'users_today' => 0,
                'daily' => [],
                'top_pages' => [],
                'per_site' => [],
                'online_sites' => 0,
                'uptime' => $this->emptyUptimeStats(),
                'deploy' => $this->emptyDeployStats(),
                'runtime' => $this->getRuntimeStats(100.0),
            ];
        }

        $siteIds = $sites->pluck('id');
        $snapshots = $this->analyticsRowsForSites($siteIds);

        $siteStats = $sites->map(function (Site $site) use ($snapshots): array {
            $siteRows = $snapshots->where('site_id', $site->id);

            return [
                'id' => $site->id,
                'name' => $site->name,
                'slug' => $site->slug,
                'pages_count' => $site->pages_count,
                'visitors' => (int) $siteRows->sum('visitors'),
                'pageviews' => (int) $siteRows->sum('pageviews'),
                'is_up' => $site->latestUptimeCheck?->is_up,
                'last_check_at' => $site->latestUptimeCheck?->checked_at,
            ];
        })->sortByDesc('visitors')->values()->all();

        $daily = $snapshots
            ->groupBy('date')
            ->map(fn (Collection $group) => [
                'date' => $group->first()['date'],
                'visitors' => (int) $group->sum('visitors'),
                'pageviews' => (int) $group->sum('pageviews'),
            ])
            ->sortBy('date')
            ->values()
            ->all();

        $uptime = $this->getGlobalUptimeStats($siteIds);

        return [
            'mode' => 'global',
            'site_name' => null,
            'total_visitors' => (int) $snapshots->sum('visitors'),
            'total_pageviews' => (int) $snapshots->sum('pageviews'),
            'users_today' => $this->getUsersToday($siteIds),
            'daily' => $daily,
            'top_pages' => $this->hydrateTopPages(
                $snapshots
                    ->groupBy('page_id')
                    ->map(fn (Collection $group) => [
                        'page_id' => $group->first()['page_id'],
                        'pageviews' => (int) $group->sum('pageviews'),
                        'visitors' => (int) $group->sum('visitors'),
                    ])
                    ->sortByDesc('pageviews')
                    ->take(10)
                    ->values()
                    ->all()
            ),
            'per_site' => $siteStats,
            'online_sites' => $this->countOnlineSites($sites),
            'uptime' => $uptime,
            'deploy' => $this->getDeployStats(),
            'runtime' => $this->getRuntimeStats($uptime['uptime_percent']),
        ];
    }

    private function analyticsRowsForSites(Collection $siteIds): Collection
    {
        return AnalyticsSnapshot::query()
            ->join('pages', 'pages.id', '=', 'analytics_snapshots.page_id')
            ->whereIn('pages.site_id', $siteIds)
            ->where('analytics_snapshots.date', '>=', now()->subDays($this->days)->toDateString())
            ->selectRaw('pages.site_id, analytics_snapshots.page_id, analytics_snapshots.date, SUM(analytics_snapshots.visitors) as visitors, SUM(analytics_snapshots.pageviews) as pageviews')
            ->groupBy('pages.site_id', 'analytics_snapshots.page_id', 'analytics_snapshots.date')
            ->orderBy('analytics_snapshots.date')
            ->get()
            ->map(fn ($row) => [
                'site_id' => $row->site_id,
                'page_id' => $row->page_id,
                'date' => $row->date,
                'visitors' => (int) $row->visitors,
                'pageviews' => (int) $row->pageviews,
            ]);
    }

    private function getUsersToday(Collection $siteIds): int
    {
        return (int) AnalyticsSnapshot::query()
            ->join('pages', 'pages.id', '=', 'analytics_snapshots.page_id')
            ->whereIn('pages.site_id', $siteIds)
            ->whereDate('analytics_snapshots.date', now()->toDateString())
            ->sum('analytics_snapshots.visitors');
    }

    private function hydrateTopPages(array $topPages): array
    {
        $pagesById = Page::query()
            ->with('site')
            ->whereIn('id', collect($topPages)->pluck('page_id')->filter()->all())
            ->get()
            ->keyBy('id');

        return collect($topPages)->map(function (array $item) use ($pagesById): array {
            $page = $pagesById->get($item['page_id']);

            return [
                'page_id' => $item['page_id'],
                'title' => $page?->title ?: ($page?->url_path ?: 'Untitled page'),
                'url_path' => $page?->url_path ?: '/',
                'site_id' => $page?->site_id,
                'site_name' => $page?->site?->name,
                'visitors' => (int) ($item['visitors'] ?? 0),
                'pageviews' => (int) ($item['pageviews'] ?? 0),
            ];
        })->values()->all();
    }

    private function countOnlineSites(Collection $sites): int
    {
        return $sites->filter(function (Site $site): bool {
            if (! $site->latestUptimeCheck) {
                return false;
            }

            return $site->latestUptimeCheck->is_up
                && $site->latestUptimeCheck->checked_at->greaterThanOrEqualTo(now()->subMinutes(15));
        })->count();
    }

    private function getUptimeStats(Site $site): array
    {
        $checks = $site->uptimeChecks()
            ->where('checked_at', '>=', now()->subDays($this->days))
            ->orderBy('checked_at')
            ->get();

        return $this->buildUptimeStats($checks);
    }

    private function getGlobalUptimeStats(Collection $siteIds): array
    {
        $checks = UptimeCheck::query()
            ->whereIn('site_id', $siteIds)
            ->where('checked_at', '>=', now()->subDays($this->days))
            ->orderBy('checked_at')
            ->get();

        return $this->buildUptimeStats($checks);
    }

    private function buildUptimeStats(Collection $checks): array
    {
        if ($checks->isEmpty()) {
            return $this->emptyUptimeStats();
        }

        $totalChecks = $checks->count();
        $upChecks = $checks->where('is_up', true)->count();
        $uptimePercent = $totalChecks > 0
            ? round(($upChecks / $totalChecks) * 100, 2)
            : 100.0;

        $responseTimes = $checks
            ->pluck('response_time_ms')
            ->filter(fn ($ms) => $ms !== null)
            ->map(fn ($ms) => (int) $ms)
            ->sort()
            ->values();

        $avgResponseTime = $responseTimes->isEmpty() ? 0 : (int) round($responseTimes->avg());
        $p95ResponseTime = $this->percentile($responseTimes, 95);

        return [
            'uptime_percent' => $uptimePercent,
            'avg_response_time' => $avgResponseTime,
            'p95_response_time' => $p95ResponseTime,
            'total_checks' => $totalChecks,
            'downtime_events' => $totalChecks - $upChecks,
            'recent' => $checks->take(-50)->map(fn ($c) => [
                'is_up' => $c->is_up,
                'response_time_ms' => $c->response_time_ms,
                'checked_at' => $c->checked_at->format('H:i'),
            ])->values()->toArray(),
        ];
    }

    private function getDeployStats(?string $siteId = null): array
    {
        $logs = DeployLog::query()
            ->when($siteId, fn ($query) => $query->where('site_id', $siteId))
            ->where('created_at', '>=', now()->subDays($this->days))
            ->get(['status', 'duration_ms']);

        if ($logs->isEmpty()) {
            return $this->emptyDeployStats();
        }

        $successful = $logs->where('status', 'success')->count();
        $durations = $logs->pluck('duration_ms')->filter()->map(fn ($ms) => (int) $ms);

        return [
            'total' => $logs->count(),
            'successful' => $successful,
            'success_rate' => round(($successful / $logs->count()) * 100, 1),
            'avg_duration_ms' => $durations->isEmpty() ? 0 : (int) round($durations->avg()),
        ];
    }

    private function getRuntimeStats(float $uptimePercent): array
    {
        $periodMinutes = max(1, $this->days * 24 * 60);
        $downtimeMinutes = (int) round(((100 - $uptimePercent) / 100) * $periodMinutes);

        return [
            'runtime_percent' => round($uptimePercent, 2),
            'downtime_minutes' => $downtimeMinutes,
            'downtime_hours' => round($downtimeMinutes / 60, 1),
        ];
    }

    private function percentile(Collection $sortedValues, int $percentile): int
    {
        if ($sortedValues->isEmpty()) {
            return 0;
        }

        $index = (int) ceil(($percentile / 100) * $sortedValues->count()) - 1;
        $index = max(0, min($index, $sortedValues->count() - 1));

        return (int) $sortedValues->get($index);
    }

    private function emptyUptimeStats(): array
    {
        return [
            'uptime_percent' => 100.0,
            'avg_response_time' => 0,
            'p95_response_time' => 0,
            'total_checks' => 0,
            'downtime_events' => 0,
            'recent' => [],
        ];
    }

    private function emptyDeployStats(): array
    {
        return [
            'total' => 0,
            'successful' => 0,
            'success_rate' => 0.0,
            'avg_duration_ms' => 0,
        ];
    }
}
