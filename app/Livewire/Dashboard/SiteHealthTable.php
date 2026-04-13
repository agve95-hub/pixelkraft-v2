<?php

namespace App\Livewire\Dashboard;

use App\Models\Site;
use App\Support\SeoIssueSummary;
use App\Support\SiteAccess;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class SiteHealthTable extends Component
{
    public function render(): View
    {
        $sites = SiteAccess::query()
            ->withCount(['pages', 'notifications' => fn ($q) => $q->where('is_read', false)->whereIn('type', ['deploy_failed', 'uptime_down'])])
            ->with(['latestUptimeCheck', 'pages'])
            ->orderBy('name')
            ->get()
            ->map(function (Site $site) {
                $seoIssues = SeoIssueSummary::openCountForSite($site);

                $uptimeCheck = $site->latestUptimeCheck;
                $uptimeHistory = $site->uptimeChecks()
                    ->where('checked_at', '>=', now()->subDays(30))
                    ->get();
                $uptimePercent = $uptimeHistory->count() > 0
                    ? round(($uptimeHistory->where('is_up', true)->count() / $uptimeHistory->count()) * 100, 1)
                    : null;

                return [
                    'site' => $site,
                    'is_up' => $uptimeCheck?->is_up,
                    'ssl_status' => $site->ssl_status,
                    'uptime_percent' => $uptimePercent,
                    'response_time' => $uptimeCheck?->responseTimeFormatted(),
                    'pages_count' => $site->pages_count,
                    'error_count' => $site->notifications_count,
                    'seo_issues' => $seoIssues,
                ];
            });

        return view('livewire.dashboard.site-health-table', [
            'sites' => $sites,
        ]);
    }
}
