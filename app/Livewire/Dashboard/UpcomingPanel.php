<?php

namespace App\Livewire\Dashboard;

use App\Models\Reminder;
use App\Models\Report;
use App\Support\SchemaState;
use App\Support\SiteAccess;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class UpcomingPanel extends Component
{
    public function render(): View
    {
        $upcoming = collect();
        $hasReminders = SchemaState::hasTable('reminders');
        $hasReports = SchemaState::hasTable('reports');

        // Load all visible sites once to avoid N+1 queries across the checks below.
        $allSites = SiteAccess::query()->get();
        $visibleSiteIds = $allSites->pluck('id');

        // Batch the monthly-report check: one query for all sites instead of
        // one exists() call per site inside the loop.
        $sitesWithReportThisMonth = $hasReports
            ? Report::query()
                ->whereIn('site_id', $visibleSiteIds)
                ->whereYear('report_date', now()->year)
                ->whereMonth('report_date', now()->month)
                ->pluck('site_id')
                ->flip()
            : collect(); // keyed by site_id for O(1) membership checks

        foreach ($allSites->where('ssl_status', 'pending') as $site) {
            $upcoming->push([
                'icon' => 'shield-exclamation',
                'color' => 'red',
                'title' => 'Resolve SSL provisioning',
                'subtitle' => $site->name,
                'date' => now(),
                'overdue' => true,
                'href' => route('sites.settings', $site),
            ]);
        }

        foreach ($allSites->filter(fn ($s) => empty($s->domain)) as $site) {
            $upcoming->push([
                'icon' => 'globe-alt',
                'color' => 'blue',
                'title' => 'Configure custom domain',
                'subtitle' => $site->name,
                'date' => now()->addDay(),
                'overdue' => false,
                'href' => route('sites.settings', $site),
            ]);
        }

        if ($hasReminders) {
            Reminder::query()
                ->whereIn('site_id', $visibleSiteIds)
                ->where('is_done', false)
                ->whereNotNull('due_date')
                ->with('site:id,name,slug')
                ->where('due_date', '>=', now()->subDay()->toDateString())
                ->where('due_date', '<=', now()->addDays(90)->toDateString())
                ->orderBy('due_date')
                ->limit(8)
                ->get()
                ->each(function (Reminder $reminder) use (&$upcoming) {
                    $site = $reminder->site;
                    if (! $site) {
                        return;
                    }
                    $due = $reminder->due_date->startOfDay();
                    $overdue = $due->isPast() && ! $due->isToday();

                    $upcoming->push([
                        'icon' => 'clock',
                        'color' => $overdue ? 'red' : 'zinc',
                        'title' => $reminder->title,
                        'subtitle' => $site->name,
                        'date' => $due,
                        'overdue' => $overdue,
                        'href' => route('sites.reminders', $site),
                    ]);
                });
        }

        if ($hasReports) {
            foreach ($allSites as $site) {
                if (! $sitesWithReportThisMonth->has($site->id)) {
                    $upcoming->push([
                        'icon' => 'clipboard-document',
                        'color' => 'zinc',
                        'title' => 'Add monthly site report',
                        'subtitle' => $site->name,
                        'date' => now()->endOfMonth()->startOfDay(),
                        'overdue' => false,
                        'href' => route('sites.reports', $site),
                    ]);
                }
            }
        }

        foreach ($allSites->filter(fn ($s) => empty($s->ga_property_id))->take(3) as $site) {
            $upcoming->push([
                'icon' => 'chart-bar',
                'color' => 'zinc',
                'title' => 'Add analytics tracking',
                'subtitle' => $site->name,
                'date' => now()->addDays(7),
                'overdue' => false,
                'href' => route('sites.settings', $site),
            ]);
        }

        $sorted = $upcoming->sortBy(fn (array $item) => $item['date']->timestamp)->values();
        $totalCount = $sorted->count();

        return view('livewire.dashboard.upcoming-panel', [
            'upcoming' => $sorted->take(5),
            'totalCount' => $totalCount,
        ]);
    }
}
