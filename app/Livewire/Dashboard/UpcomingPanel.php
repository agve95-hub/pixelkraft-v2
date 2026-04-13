<?php

namespace App\Livewire\Dashboard;

use App\Models\Reminder;
use App\Models\Report;
use App\Support\SiteAccess;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class UpcomingPanel extends Component
{
    public function render(): View
    {
        $upcoming = collect();

        $visibleSiteIds = SiteAccess::query()->pluck('id');

        $sslPending = SiteAccess::query()->where('ssl_status', 'pending')->get();
        foreach ($sslPending as $site) {
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

        $noDomain = SiteAccess::query()
            ->where(function ($query) {
                $query->whereNull('domain')->orWhere('domain', '');
            })
            ->get();
        foreach ($noDomain as $site) {
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

        foreach (SiteAccess::query()->get() as $site) {
            $hasReportThisMonth = Report::query()
                ->where('site_id', $site->id)
                ->whereYear('report_date', now()->year)
                ->whereMonth('report_date', now()->month)
                ->exists();

            if (! $hasReportThisMonth) {
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

        $noAnalytics = SiteAccess::query()
            ->where(function ($query) {
                $query->whereNull('ga_property_id')->orWhere('ga_property_id', '');
            })
            ->limit(3)
            ->get();
        foreach ($noAnalytics as $site) {
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
