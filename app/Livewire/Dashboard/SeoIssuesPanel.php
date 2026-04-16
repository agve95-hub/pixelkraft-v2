<?php

namespace App\Livewire\Dashboard;

use App\Models\SeoIssue;
use App\Support\SchemaState;
use App\Support\SiteAccess;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class SeoIssuesPanel extends Component
{
    public function render(): View
    {
        if (! SchemaState::hasTable('seo_issues')) {
            return view('livewire.dashboard.seo-issues-panel', [
                'issues' => collect(),
                'totalCount' => 0,
            ]);
        }

        $visibleSiteIds = SiteAccess::query()->pluck('id');

        $issues = SeoIssue::query()
            ->open()
            ->whereIn('site_id', $visibleSiteIds)
            ->whereNotNull('page_id')
            ->with(['page' => fn ($q) => $q->select('id', 'site_id', 'url_path', 'title'), 'site:id,name'])
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get()
            ->map(fn (SeoIssue $issue) => [
                'severity' => (string) $issue->severity,
                'message' => (string) $issue->message,
                'site' => (string) ($issue->site?->name ?? 'Unknown'),
                'page' => $issue->page,
            ]);

        $totalCount = SeoIssue::query()
            ->open()
            ->whereIn('site_id', $visibleSiteIds)
            ->count();

        return view('livewire.dashboard.seo-issues-panel', [
            'issues' => $issues,
            'totalCount' => $totalCount,
        ]);
    }
}
