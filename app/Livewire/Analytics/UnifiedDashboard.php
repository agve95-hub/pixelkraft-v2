<?php

namespace App\Livewire\Analytics;

use App\Models\Site;
use App\Models\UptimeCheck;
use App\Services\AnalyticsAggregator;
use Livewire\Component;

class UnifiedDashboard extends Component
{
    public ?string $siteId = null;
    public int $days = 30;

    public function render()
    {
        $aggregator = app(AnalyticsAggregator::class);

        if ($this->siteId) {
            $site = Site::with('pages')->findOrFail($this->siteId);
            $stats = $aggregator->getSiteStats($site, $this->days);
            $uptime = $this->getUptimeStats($site);
            $sites = collect([$site]);
        } else {
            $sites = Site::where('is_active', true)->with('pages', 'latestUptimeCheck')->get();
            $stats = $this->getGlobalStats($aggregator, $sites);
            $uptime = null;
        }

        return view('livewire.analytics.unified-dashboard', [
            'sites'  => $sites,
            'stats'  => $stats,
            'uptime' => $uptime,
        ]);
    }

    private function getGlobalStats(AnalyticsAggregator $aggregator, $sites): array
    {
        $totalVisitors = 0;
        $totalPageviews = 0;
        $siteStats = [];

        foreach ($sites as $site) {
            $s = $aggregator->getSiteStats($site, $this->days);
            $totalVisitors += $s['total_visitors'];
            $totalPageviews += $s['total_pageviews'];
            $siteStats[] = [
                'name'      => $site->name,
                'slug'      => $site->slug,
                'id'        => $site->id,
                'visitors'  => $s['total_visitors'],
                'pageviews' => $s['total_pageviews'],
                'is_up'     => $site->latestUptimeCheck?->is_up ?? null,
            ];
        }

        usort($siteStats, fn ($a, $b) => $b['visitors'] <=> $a['visitors']);

        return [
            'total_visitors'  => $totalVisitors,
            'total_pageviews' => $totalPageviews,
            'per_site'        => $siteStats,
            'daily'           => [],
            'top_pages'       => [],
        ];
    }

    private function getUptimeStats(Site $site): array
    {
        $checks = $site->uptimeChecks()
            ->where('checked_at', '>=', now()->subDays($this->days))
            ->orderBy('checked_at')
            ->get();

        $totalChecks = $checks->count();
        $upChecks = $checks->where('is_up', true)->count();
        $uptimePercent = $totalChecks > 0 ? round(($upChecks / $totalChecks) * 100, 2) : 100;
        $avgResponseTime = (int) $checks->avg('response_time_ms');

        return [
            'uptime_percent'    => $uptimePercent,
            'avg_response_time' => $avgResponseTime,
            'total_checks'      => $totalChecks,
            'downtime_events'   => $totalChecks - $upChecks,
            'recent' => $checks->take(-50)->map(fn ($c) => [
                'is_up'            => $c->is_up,
                'response_time_ms' => $c->response_time_ms,
                'checked_at'       => $c->checked_at->format('H:i'),
            ])->values()->toArray(),
        ];
    }
}
