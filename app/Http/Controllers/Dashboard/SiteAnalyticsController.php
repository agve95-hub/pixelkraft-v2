<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\DeployLog;
use App\Models\DeploymentRelease;
use App\Models\Site;
use App\Models\UptimeCheck;
use App\Services\AnalyticsAggregator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class SiteAnalyticsController
{
    public function __invoke(Request $request, Site $site, AnalyticsAggregator $analytics): View
    {
        $zeroStats = ['daily' => [], 'total_visitors' => 0, 'total_pageviews' => 0, 'avg_bounce_rate' => 0.0, 'avg_session_sec' => 0, 'top_pages' => [], 'traffic_label' => 'All sources'];
        $zeroEvents = ['total_events' => 0, 'page_views' => 0, 'forms' => 0, 'interactions' => 0, 'top_events' => []];

        try {
            $stats = Schema::hasTable('analytics_snapshots')
                ? $analytics->getSiteStats($site, 30)
                : $zeroStats;
        } catch (\Throwable) {
            $stats = $zeroStats;
        }

        $daily = collect($stats['daily'] ?? []);
        $trafficTotal = (int) ($stats['total_visitors'] ?? 0);

        $last7 = (int) $daily->slice(-7)->sum('visitors');
        $prev7 = (int) $daily->slice(-14, 7)->sum('visitors');
        $trafficTrendPercent = $prev7 > 0
            ? (int) round((($last7 - $prev7) / $prev7) * 100)
            : null;

        try {
            $checks = Schema::hasTable('uptime_checks')
                ? UptimeCheck::query()
                    ->where('site_id', $site->id)
                    ->where('checked_at', '>=', now()->subDays(30))
                    ->orderBy('checked_at')
                    ->get(['checked_at', 'is_up', 'is_degraded', 'response_time_ms'])
                : collect();
        } catch (\Throwable) {
            $checks = collect();
        }

        $uptimePercent = $checks->isEmpty()
            ? 100.0
            : round(($checks->where('is_up', true)->count() / max(1, $checks->count())) * 100, 1);

        $dailyBars = collect(range(29, 0))->map(function (int $daysAgo) use ($checks) {
            $day = now()->subDays($daysAgo)->toDateString();
            $dayChecks = $checks->filter(fn (UptimeCheck $check) => $check->checked_at->toDateString() === $day);

            if ($dayChecks->isEmpty()) {
                return 'unknown';
            }

            if ($dayChecks->contains(fn (UptimeCheck $check) => ! $check->is_up)) {
                return 'down';
            }

            if ($dayChecks->contains(fn (UptimeCheck $check) => (bool) $check->is_degraded)) {
                return 'degraded';
            }

            return 'up';
        })->values();

        $upDays = $dailyBars->filter(fn ($v) => $v === 'up')->count();
        $degradedDays = $dailyBars->filter(fn ($v) => $v === 'degraded')->count();
        $downDays = $dailyBars->filter(fn ($v) => $v === 'down')->count();

        $responseSeries = $checks->pluck('response_time_ms')
            ->filter(fn ($ms) => $ms !== null)
            ->map(fn ($ms) => (int) $ms)
            ->values();

        $avgResponseMs = $responseSeries->isEmpty() ? 0 : (int) round($responseSeries->avg());
        $sorted = $responseSeries->sort()->values();
        $p95ResponseMs = $sorted->isEmpty()
            ? 0
            : (int) $sorted->get(max(0, (int) ceil($sorted->count() * 0.95) - 1));

        $trafficChart = $this->buildTrafficChart($daily);
        $responseChart = $this->buildResponseChart($responseSeries);

        try {
            $deploys = Schema::hasTable('deploy_logs')
                ? DeployLog::query()->where('site_id', $site->id)->orderByDesc('created_at')->limit(25)->get()
                : collect();
        } catch (\Throwable) {
            $deploys = collect();
        }

        $currentRelease = Schema::hasTable('deployment_releases')
            ? $site->currentDeploymentRelease()->first()
            : null;
        $releaseCount = Schema::hasTable('deployment_releases')
            ? DeploymentRelease::query()->where('site_id', $site->id)->count()
            : 0;

        try {
            $eventSummary = Schema::hasTable('analytics_events')
                ? $analytics->summarizeSiteEvents($site, 30)
                : $zeroEvents;
        } catch (\Throwable) {
            $eventSummary = $zeroEvents;
        }

        return view('dashboard.sites.analytics', [
            'site' => $site,
            'trafficTotal' => $trafficTotal,
            'trafficTrendPercent' => $trafficTrendPercent,
            'uptimePercent' => $uptimePercent,
            'dailyBars' => $dailyBars,
            'upDays' => $upDays,
            'degradedDays' => $degradedDays,
            'downDays' => $downDays,
            'avgResponseMs' => $avgResponseMs,
            'p95ResponseMs' => $p95ResponseMs,
            'trafficChart' => $trafficChart,
            'responseChart' => $responseChart,
            'deploys' => $deploys,
            'deployCount' => $deploys->count(),
            'currentRelease' => $currentRelease,
            'releaseCount' => $releaseCount,
            'eventSummary' => $eventSummary,
        ]);
    }

    /**
     * @param  Collection<int, array{date: string, visitors: int, pageviews: int}>  $daily
     * @return array{width: int, height: int, line_path: string, area_path: string}
     */
    private function buildTrafficChart($daily): array
    {
        $vbW = 820;
        $vbH = 220;
        $pad = 16;
        $plotW = $vbW - ($pad * 2);
        $plotH = $vbH - ($pad * 2);

        $series = collect(range(29, 0))->map(function (int $daysAgo) use ($daily) {
            $day = now()->subDays($daysAgo)->toDateString();
            $row = $daily->firstWhere('date', $day);

            return [
                'day' => $day,
                'visitors' => (int) ($row['visitors'] ?? 0),
            ];
        })->values();

        $maxTraffic = max(1, (int) $series->max('visitors'));
        $pointCount = max(1, $series->count() - 1);
        $chartPoints = [];

        foreach ($series as $i => $point) {
            $x = $pad + (($i / $pointCount) * $plotW);
            $y = $pad + $plotH - (($point['visitors'] / $maxTraffic) * $plotH);
            $chartPoints[] = round($x, 2).','.round($y, 2);
        }

        if (empty($chartPoints)) {
            return ['width' => $vbW, 'height' => $vbH, 'line_path' => '', 'area_path' => ''];
        }

        $lineD = 'M '.implode(' L ', $chartPoints);
        $firstX = (float) explode(',', $chartPoints[0])[0];
        $lastX = (float) explode(',', $chartPoints[count($chartPoints) - 1])[0];
        $baseY = $pad + $plotH;
        $areaD = $lineD.' L '.$lastX.' '.$baseY.' L '.$firstX.' '.$baseY.' Z';

        return [
            'width' => $vbW,
            'height' => $vbH,
            'line_path' => $lineD,
            'area_path' => $areaD,
        ];
    }

    /**
     * @param  Collection<int, int>  $responseSeries
     * @return array{width: int, height: int, path: string}
     */
    private function buildResponseChart($responseSeries): array
    {
        $rW = 500;
        $rH = 120;
        $rPad = 8;
        $rPlotW = $rW - ($rPad * 2);
        $rPlotH = $rH - ($rPad * 2);
        $responseMax = max(1, (int) $responseSeries->max());
        $responseCount = max(1, $responseSeries->count() - 1);
        $rPoints = [];

        foreach ($responseSeries as $i => $ms) {
            $x = $rPad + (($i / $responseCount) * $rPlotW);
            $y = $rPad + $rPlotH - (($ms / $responseMax) * $rPlotH);
            $rPoints[] = round($x, 2).','.round($y, 2);
        }

        if (empty($rPoints)) {
            return ['width' => $rW, 'height' => $rH, 'path' => ''];
        }

        return [
            'width' => $rW,
            'height' => $rH,
            'path' => 'M '.implode(' L ', $rPoints),
        ];
    }
}
