<?php

namespace App\Livewire\Dashboard;

use App\Models\Site;
use App\Support\SchemaState;
use App\Support\SiteAccess;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class SiteHealthTable extends Component
{
    public function render(): View
    {
        $thirtyDaysAgo = now()->subDays(30);
        $hasNotifications = SchemaState::hasTable('notifications');
        $hasSeoIssues = SchemaState::hasTable('seo_issues');

        $withCounts = [
            'pages',
        ];

        if ($hasNotifications) {
            $withCounts['notifications'] = fn ($q) => $q
                ->where('is_read', false)
                ->whereIn('type', ['deploy_failed', 'uptime_down']);
        }

        if ($hasSeoIssues) {
            $withCounts['seoIssues'] = fn ($q) => $q->whereNull('resolved_at');
        }

        // Eager-load uptime history and SEO issue count in the bulk query to
        // avoid N+1 queries (one per site) in the map() loop below.
        $sites = SiteAccess::query()
            ->withCount($withCounts)
            ->with([
                'latestUptimeCheck',
                'uptimeChecks' => fn ($q) => $q->where('checked_at', '>=', $thirtyDaysAgo),
            ])
            ->orderBy('name')
            ->get()
            ->map(function (Site $site) use ($hasNotifications, $hasSeoIssues) {
                $uptimeHistory = $site->uptimeChecks; // already loaded
                $uptimePercent = $uptimeHistory->count() > 0
                    ? round(($uptimeHistory->where('is_up', true)->count() / $uptimeHistory->count()) * 100, 1)
                    : null;

                return [
                    'site' => $site,
                    'is_up' => $site->latestUptimeCheck?->is_up,
                    'ssl_status' => $site->ssl_status,
                    'uptime_percent' => $uptimePercent,
                    'response_time' => $site->latestUptimeCheck?->responseTimeFormatted(),
                    'pages_count' => $site->pages_count,
                    'error_count' => $hasNotifications ? (int) ($site->notifications_count ?? 0) : 0,
                    'seo_issues' => $hasSeoIssues ? (int) ($site->seo_issues_count ?? 0) : 0,
                ];
            });

        return view('livewire.dashboard.site-health-table', [
            'sites' => $sites,
        ]);
    }
}
