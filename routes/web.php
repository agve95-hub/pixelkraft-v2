<?php

use App\Http\Controllers\Dashboard\SiteAnalyticsController;
use App\Http\Controllers\EditorPreviewController;
use App\Http\Controllers\InvoicePdfController;
use App\Models\AnalyticsSnapshot;
use App\Models\BlogPost;
use App\Models\FormSubmission;
use App\Models\Notification;
use App\Models\Page;
use App\Models\Site;
use App\Models\UptimeCheck;
use App\Support\SchemaState;
use App\Support\SeoIssueSummary;
use App\Support\SiteAccess;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// ── Health check (no auth) ───────────────────────
// Used by post-deploy CI smoke tests, load balancers, and external uptime monitors.
Route::get('/health', function () {
    $checks = [];
    $healthy = true;

    // Database connectivity
    try {
        DB::connection()->getPdo();
        $checks['database'] = 'ok';
    } catch (Throwable) {
        $checks['database'] = 'error';
        $healthy = false;
    }

    // Cache / Redis connectivity
    try {
        $key = 'pixelkraft:health:'.uniqid('', true);
        Cache::put($key, 1, 10);
        Cache::forget($key);
        $checks['cache'] = 'ok';
    } catch (Throwable) {
        $checks['cache'] = 'error';
        $healthy = false;
    }

    return response()->json([
        'status' => $healthy ? 'ok' : 'degraded',
        'checks' => $checks,
        'timestamp' => now()->toIso8601String(),
    ], $healthy ? 200 : 503);
})->name('health');

// ── Guest ───────────────────────────────────────
Route::get('/', fn () => redirect()->route('login'));

// ── Dashboard (auth required) ───────────────────
Route::middleware(['auth'])->scopeBindings()->prefix('dashboard')->group(function () {

    Route::get('/', function () {
        $visibleSiteIds = SiteAccess::query()->pluck('id');
        $seoIssueCount = SchemaState::hasTable('seo_issues')
            ? SeoIssueSummary::openCountForSiteIds($visibleSiteIds)
            : 0;

        $totalSites = $visibleSiteIds->count();
        $totalPages = Page::query()->whereIn('site_id', $visibleSiteIds)->count();

        $latestChecks = UptimeCheck::query()
            ->whereIn('site_id', $visibleSiteIds)
            ->select('site_id', DB::raw('MAX(checked_at) as last_check'))
            ->groupBy('site_id')
            ->get()
            ->pluck('last_check', 'site_id');

        $uptimePercent = 0;
        if ($latestChecks->isNotEmpty()) {
            $upCount = UptimeCheck::query()
                ->whereIn(DB::raw("CONCAT(site_id, '|', checked_at)"),
                    $latestChecks->map(fn ($date, $id) => "{$id}|{$date}")->values()
                )->where('is_up', true)->count();
            $uptimePercent = round(($upCount / max(1, $latestChecks->count())) * 100, 1);
        }

        $unreadMessages = FormSubmission::query()
            ->whereIn('site_id', $visibleSiteIds)
            ->where('is_read', false)
            ->where('is_spam', false)
            ->count();

        $errorCount = Notification::query()
            ->whereIn('site_id', $visibleSiteIds)
            ->where('is_read', false)
            ->whereIn('type', ['deploy_failed', 'uptime_down', 'ssl_expiring'])
            ->count();

        $activeSites = SiteAccess::query()
            ->with(['latestUptimeCheck', 'pages:id,site_id,title,meta_description,seo_score'])
            ->withCount('pages')
            ->orderBy('name')
            ->get();

        $trafficRows = collect();
        if ($activeSites->isNotEmpty()) {
            $trafficRows = AnalyticsSnapshot::query()
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

            return ['day' => $day, 'label' => $date->format('M j'), 'visitors' => (int) ($trafficRows[$day] ?? 0)];
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
            $chartPoints[] = round($x, 2).','.round($y, 2);
        }
        $lineD = 'M '.implode(' L ', $chartPoints);
        $firstX = (float) explode(',', $chartPoints[0])[0];
        $lastX = (float) explode(',', $chartPoints[count($chartPoints) - 1])[0];
        $baseY = $pad + $plotH;
        $areaD = $lineD.' L '.$lastX.' '.$baseY.' L '.$firstX.' '.$baseY.' Z';

        $siteInsights = $activeSites->take(2)->map(function (Site $site) {
            $checks = UptimeCheck::query()
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
                ->filter(fn ($ms) => $ms !== null)->take(-24)->map(fn ($ms) => (int) $ms)->values();

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

        $sitesDown = $activeSites->filter(fn ($site) => $site->latestUptimeCheck?->is_up === false)->count();

        return Inertia::render('dashboard/index', [
            'seoIssueCount' => $seoIssueCount,
            'totalSites' => $totalSites,
            'totalPages' => $totalPages,
            'uptimePercent' => $uptimePercent,
            'unreadMessages' => $unreadMessages,
            'errorCount' => $errorCount,
            'trafficSeries' => $trafficSeries,
            'trafficVisitors' => $trafficVisitors,
            'vbW' => $vbW,
            'vbH' => $vbH,
            'pad' => $pad,
            'lineD' => $lineD,
            'areaD' => $areaD,
            'siteInsights' => $siteInsights,
            'sitesDown' => $sitesDown,
        ]);
    })->name('dashboard');

    // Sites
    Route::get('/sites', function () {
        $sites = SiteAccess::query()
            ->withCount('pages')
            ->with('latestDeploy', 'latestUptimeCheck')
            ->orderBy('name')
            ->get();

        return Inertia::render('sites/index', ['sites' => $sites]);
    })->name('sites.index');
    Route::get('/sites/create', function () {
        return Inertia::render('sites/create', [
            'projectTypes' => config('pixelkraft.project_types', ['static_html', 'react', 'vue', 'nextjs', 'nuxt', 'astro', 'hugo', 'eleventy', 'svelte', 'php_site', 'custom']),
        ]);
    })->name('sites.create');
    Route::post('/sites', function (\Illuminate\Http\Request $request) {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'project_type' => 'required|string',
            'source_type' => 'required|string|in:github,server_path,upload',
            'repo_url' => 'nullable|string|max:500',
            'branch' => 'nullable|string|max:100',
            'build_command' => 'nullable|string|max:500',
            'github_token' => 'nullable|string|max:500',
            'server_path' => 'nullable|string|max:500',
            'domain' => 'nullable|string|max:253',
            'ssl_provider' => 'nullable|string',
            'client_first_name' => 'nullable|string|max:255',
            'client_last_name' => 'nullable|string|max:255',
            'client_email' => 'nullable|email|max:255',
            'client_company' => 'nullable|string|max:255',
        ]);

        $site = Site::create([
            'user_id' => auth()->id(),
            'name' => $validated['name'],
            'slug' => \Illuminate\Support\Str::slug($validated['name']),
            'project_type' => $validated['project_type'],
            'repo_url' => $validated['repo_url'] ?? null,
            'branch' => $validated['branch'] ?? 'main',
            'build_command' => $validated['build_command'] ?? null,
            'github_token' => $validated['github_token'] ?? null,
            'deploy_path' => $validated['server_path'] ?? null,
            'domain' => $validated['domain'] ?? null,
            'ssl_provider' => $validated['ssl_provider'] ?? 'letsencrypt',
            'client_first_name' => $validated['client_first_name'] ?? null,
            'client_last_name' => $validated['client_last_name'] ?? null,
            'client_email' => $validated['client_email'] ?? null,
            'client_company' => $validated['client_company'] ?? null,
        ]);

        if ($validated['source_type'] === 'github' && !empty($validated['repo_url'])) {
            \App\Jobs\CloneRepoJob::dispatch($site);
        }

        return response()->json(['siteId' => $site->id]);
    })->name('sites.store');
    Route::get('/sites/{siteId}/import-status', function (string $siteId) {
        $site = SiteAccess::findOrFail($siteId);
        return response()->json([
            'status' => $site->deploy_status,
            'lastDeployedAt' => $site->last_deployed_at,
            'deployLog' => $site->latestDeploy?->only('id', 'status', 'steps'),
        ]);
    })->name('sites.import-status');
    Route::middleware(['site.access', 'expand.site.sidebar'])->group(function () {
        Route::get('/sites/{site}', function (Site $site) {
            $site->loadCount([
                'inboxMessages as inbox_unread_count' => fn ($q) => $q->where('direction', 'inbound')->where('is_read', false),
                'invoices as invoices_unpaid_count' => fn ($q) => $q->where('status', 'unpaid'),
                'pages',
                'blogPosts',
                'contentTemplates',
                'deployLogs',
            ]);

            $visitorsToday = AnalyticsSnapshot::query()
                ->whereHas('page', fn ($q) => $q->where('site_id', $site->id))
                ->whereDate('date', today())
                ->sum('visitors');

            $visitorsLastWeek = AnalyticsSnapshot::query()
                ->whereHas('page', fn ($q) => $q->where('site_id', $site->id))
                ->whereDate('date', today()->subWeek())
                ->sum('visitors');

            $uptimeChecks = $site->uptimeChecks()
                ->latest('checked_at')
                ->limit(50)
                ->get(['is_up', 'response_time_ms']);

            $uptimePercent = $uptimeChecks->isEmpty()
                ? null
                : round($uptimeChecks->avg(fn ($check) => $check->is_up ? 1 : 0) * 100, 1);

            $responseSamples = $uptimeChecks
                ->pluck('response_time_ms')
                ->filter(fn ($value) => is_numeric($value) && (int) $value > 0)
                ->map(fn ($value) => (int) $value)
                ->sort()
                ->values();

            $p95ResponseMs = null;
            if ($responseSamples->isNotEmpty()) {
                $index = (int) floor(($responseSamples->count() - 1) * 0.95);
                $p95ResponseMs = $responseSamples->get($index);
            }

            $latestUptime = $site->uptimeChecks()
                ->latest('checked_at')
                ->first();

            $errorTypes = ['deploy_failed', 'uptime_down', 'broken_links', 'lighthouse_drop'];

            $errorCount = Notification::query()
                ->where('site_id', $site->id)
                ->whereIn('type', $errorTypes)
                ->where('is_read', false)
                ->count();

            $errorItems = Notification::query()
                ->where('site_id', $site->id)
                ->whereIn('type', $errorTypes)
                ->latest('created_at')
                ->limit(5)
                ->get();

            $seoIssues = SeoIssueSummary::openAggregatesForSite($site);

            $pages = $site->pages()
                ->withSum([
                    'analyticsSnapshots as visitors_30d' => fn ($q) => $q->where('date', '>=', now()->subDays(30)->toDateString()),
                ], 'visitors')
                ->orderByRaw('CASE WHEN url_path IS NULL OR url_path = "" THEN 1 ELSE 0 END')
                ->orderBy('url_path')
                ->limit(25)
                ->get();

            return Inertia::render('sites/show', [
                'site' => $site,
                'seoIssueCount' => SeoIssueSummary::openCountForSite($site),
                'seoWarningCount' => SeoIssueSummary::openWarningCountForSite($site),
                'visitorsToday' => (int) $visitorsToday,
                'visitorsTrendPercent' => $visitorsLastWeek > 0
                    ? (int) round((($visitorsToday - $visitorsLastWeek) / $visitorsLastWeek) * 100)
                    : null,
                'uptimePercent' => $uptimePercent,
                'latestResponseMs' => $latestUptime?->response_time_ms,
                'p95ResponseMs' => $p95ResponseMs,
                'errorCount' => $errorCount,
                'errorItems' => $errorItems,
                'seoIssues' => $seoIssues,
                'pages' => $pages,
            ]);
        })->name('sites.show');
        Route::get('/sites/{site}/inbox', fn (Site $site) => Inertia::render('sites/inbox', ['site' => $site]))->name('sites.inbox');
        Route::get('/sites/{site}/invoices', function (Site $site) {
            $invoices = $site->invoices()->orderByDesc('invoice_date')->get();
            return Inertia::render('sites/invoices', ['site' => $site, 'invoices' => $invoices]);
        })->name('sites.invoices');
        Route::post('/sites/{site}/invoices/{invoice}/mark-paid', function (Site $site, \App\Models\Invoice $invoice) {
            abort_unless($invoice->site_id === $site->id, 403);
            $invoice->update(['status' => 'paid', 'paid_at' => now()]);
            return back();
        })->name('sites.invoices.mark-paid');
        Route::post('/sites/{site}/invoices/{invoice}/duplicate', function (Site $site, \App\Models\Invoice $invoice) {
            abort_unless($invoice->site_id === $site->id, 403);
            $copy = $invoice->replicate();
            $copy->status = 'unpaid';
            $copy->paid_at = null;
            $copy->invoice_date = now()->toDateString();
            $copy->due_date = now()->addDays(30)->toDateString();
            $copy->save();
            return back();
        })->name('sites.invoices.duplicate');
        Route::delete('/sites/{site}/invoices/{invoice}', function (Site $site, \App\Models\Invoice $invoice) {
            abort_unless($invoice->site_id === $site->id, 403);
            $invoice->delete();
            return back();
        })->name('sites.invoices.destroy');
        Route::get('/sites/{site}/invoices/{invoice}/pdf', InvoicePdfController::class)->name('sites.invoices.pdf');
        Route::get('/sites/{site}/campaigns', function (Site $site) {
            return Inertia::render('sites/campaigns', ['site' => $site, 'campaigns' => $site->campaigns()->orderByDesc('created_at')->get()]);
        })->name('sites.campaigns');
        Route::post('/sites/{site}/campaigns', function (\Illuminate\Http\Request $request, Site $site) {
            $d = $request->validate(['name' => 'required|string|max:255', 'headline' => 'nullable|string|max:255', 'body' => 'nullable|string', 'cta_text' => 'nullable|string|max:100', 'cta_url' => 'nullable|url|max:500', 'trigger' => 'nullable|string|max:64', 'starts_at' => 'nullable|date', 'ends_at' => 'nullable|date|after_or_equal:starts_at', 'priority' => 'nullable|integer', 'is_dismissible' => 'boolean', 'locale' => 'nullable|string|max:10']);
            $site->campaigns()->create(array_merge($d, ['is_enabled' => false]));
            return back();
        })->name('sites.campaigns.store');
        Route::put('/sites/{site}/campaigns/{campaign}', function (\Illuminate\Http\Request $request, Site $site, \App\Models\Campaign $campaign) {
            abort_unless($campaign->site_id === $site->id, 403);
            $d = $request->validate(['name' => 'required|string|max:255', 'headline' => 'nullable|string|max:255', 'body' => 'nullable|string', 'cta_text' => 'nullable|string|max:100', 'cta_url' => 'nullable|url|max:500', 'trigger' => 'nullable|string|max:64', 'starts_at' => 'nullable|date', 'ends_at' => 'nullable|date|after_or_equal:starts_at', 'priority' => 'nullable|integer', 'is_dismissible' => 'boolean', 'locale' => 'nullable|string|max:10']);
            $campaign->update($d);
            return back();
        })->name('sites.campaigns.update');
        Route::post('/sites/{site}/campaigns/{campaign}/toggle', function (Site $site, \App\Models\Campaign $campaign) {
            abort_unless($campaign->site_id === $site->id, 403);
            $campaign->update(['is_enabled' => !$campaign->is_enabled]);
            return back();
        })->name('sites.campaigns.toggle');
        Route::post('/sites/{site}/campaigns/{campaign}/duplicate', function (Site $site, \App\Models\Campaign $campaign) {
            abort_unless($campaign->site_id === $site->id, 403);
            $new = $campaign->replicate();
            $new->name = $campaign->name . ' (copy)';
            $new->is_enabled = false;
            $new->save();
            return back();
        })->name('sites.campaigns.duplicate');
        Route::delete('/sites/{site}/campaigns/{campaign}', function (Site $site, \App\Models\Campaign $campaign) {
            abort_unless($campaign->site_id === $site->id, 403);
            $campaign->delete();
            return back();
        })->name('sites.campaigns.destroy');
        Route::get('/sites/{site}/expenses', function (Site $site) {
            $expenses = $site->expenses()->orderByDesc('expense_date')->orderByDesc('created_at')->get();
            $totals = $site->expenses()->selectRaw('currency, SUM(amount) as total')->groupBy('currency')->get();
            return Inertia::render('sites/expenses', ['site' => $site, 'expenses' => $expenses, 'totals' => $totals]);
        })->name('sites.expenses');
        Route::post('/sites/{site}/expenses', function (\Illuminate\Http\Request $request, Site $site) {
            $d = $request->validate(['label' => 'required|string|max:255', 'amount' => 'required|numeric|min:0.01', 'currency' => 'required|string|size:3', 'expense_date' => 'required|date']);
            $site->expenses()->create($d);
            return back();
        })->name('sites.expenses.store');
        Route::put('/sites/{site}/expenses/{expense}', function (\Illuminate\Http\Request $request, Site $site, \App\Models\Expense $expense) {
            abort_unless($expense->site_id === $site->id, 403);
            $d = $request->validate(['label' => 'required|string|max:255', 'amount' => 'required|numeric|min:0.01', 'currency' => 'required|string|size:3', 'expense_date' => 'required|date']);
            $expense->update($d);
            return back();
        })->name('sites.expenses.update');
        Route::delete('/sites/{site}/expenses/{expense}', function (Site $site, \App\Models\Expense $expense) {
            abort_unless($expense->site_id === $site->id, 403);
            $expense->delete();
            return back();
        })->name('sites.expenses.destroy');
        Route::delete('/sites/{site}/expenses', function (\Illuminate\Http\Request $request, Site $site) {
            $ids = $request->validate(['ids' => 'required|array', 'ids.*' => 'string'])['ids'];
            $site->expenses()->whereIn('id', $ids)->delete();
            return back();
        })->name('sites.expenses.bulk-destroy');
        Route::get('/sites/{site}/reminders', function (Site $site) {
            $reminders = $site->reminders()->orderBy('due_date')->get();
            return Inertia::render('sites/reminders', ['site' => $site, 'reminders' => $reminders]);
        })->name('sites.reminders');
        Route::post('/sites/{site}/reminders', function (\Illuminate\Http\Request $request, Site $site) {
            $d = $request->validate(['title' => 'required|string|max:255', 'due_date' => 'nullable|date', 'notes' => 'nullable|string|max:2000']);
            $site->reminders()->create($d);
            return back();
        })->name('sites.reminders.store');
        Route::put('/sites/{site}/reminders/{reminder}', function (\Illuminate\Http\Request $request, Site $site, \App\Models\Reminder $reminder) {
            abort_unless($reminder->site_id === $site->id, 403);
            $d = $request->validate(['title' => 'required|string|max:255', 'due_date' => 'nullable|date', 'notes' => 'nullable|string|max:2000']);
            $reminder->update($d);
            return back();
        })->name('sites.reminders.update');
        Route::post('/sites/{site}/reminders/{reminder}/complete', function (Site $site, \App\Models\Reminder $reminder) {
            abort_unless($reminder->site_id === $site->id, 403);
            $reminder->update(['is_done' => !$reminder->is_done]);
            return back();
        })->name('sites.reminders.complete');
        Route::delete('/sites/{site}/reminders/{reminder}', function (Site $site, \App\Models\Reminder $reminder) {
            abort_unless($reminder->site_id === $site->id, 403);
            $reminder->delete();
            return back();
        })->name('sites.reminders.destroy');
        Route::get('/sites/{site}/reports', function (\Illuminate\Http\Request $request, Site $site) {
            $query = $site->reports()->orderByDesc('report_date');
            return Inertia::render('sites/reports', ['site' => $site, 'reports' => $query->get()]);
        })->name('sites.reports');
        Route::post('/sites/{site}/reports', function (\Illuminate\Http\Request $request, Site $site) {
            $d = $request->validate(['title' => 'required|string|max:255', 'report_date' => 'required|date', 'summary' => 'nullable|string']);
            $site->reports()->create($d);
            return back();
        })->name('sites.reports.store');
        Route::put('/sites/{site}/reports/{report}', function (\Illuminate\Http\Request $request, Site $site, \App\Models\Report $report) {
            abort_unless($report->site_id === $site->id, 403);
            $d = $request->validate(['title' => 'required|string|max:255', 'report_date' => 'required|date', 'summary' => 'nullable|string']);
            $report->update($d);
            return back();
        })->name('sites.reports.update');
        Route::post('/sites/{site}/reports/{report}/duplicate', function (Site $site, \App\Models\Report $report) {
            abort_unless($report->site_id === $site->id, 403);
            $new = $report->replicate();
            $new->title = $report->title . ' (copy)';
            $new->save();
            return back();
        })->name('sites.reports.duplicate');
        Route::delete('/sites/{site}/reports/{report}', function (Site $site, \App\Models\Report $report) {
            abort_unless($report->site_id === $site->id, 403);
            $report->delete();
            return back();
        })->name('sites.reports.destroy');
        Route::get('/sites/{site}/analytics', SiteAnalyticsController::class)->name('sites.analytics');
        Route::get('/sites/{site}/maintenance', fn (Site $site) => Inertia::render('sites/maintenance', ['site' => $site]))->name('sites.maintenance');
        Route::get('/sites/{site}/maintenance/preview', function (Site $site) {
            return view('dashboard.sites.maintenance-preview', ['site' => $site]);
        })->name('sites.maintenance.preview');
        Route::get('/sites/{site}/settings', fn (Site $site) => Inertia::render('sites/settings', ['site' => $site]))->name('sites.settings');
        Route::put('/sites/{site}/settings', function (\Illuminate\Http\Request $request, Site $site) {
            $d = $request->validate([
                'name' => 'required|string|max:255',
                'domain' => 'nullable|string|max:255',
                'project_type' => 'nullable|string|max:64',
                'client_first_name' => 'nullable|string|max:100',
                'client_last_name' => 'nullable|string|max:100',
                'client_email' => 'nullable|email|max:255',
                'client_phone' => 'nullable|string|max:50',
                'client_company' => 'nullable|string|max:255',
                'client_address' => 'nullable|string|max:500',
                'client_notes' => 'nullable|string|max:2000',
                'billing_cycle' => 'nullable|string|max:32',
                'monthly_retainer' => 'nullable|numeric|min:0',
                'ga_property_id' => 'nullable|string|max:64',
                'gtm_id' => 'nullable|string|max:64',
                'google_ads_id' => 'nullable|string|max:64',
                'cf_zone_id' => 'nullable|string|max:64',
                'cf_api_token' => 'nullable|string|max:500',
                'smtp_host' => 'nullable|string|max:255',
                'smtp_port' => 'nullable|integer|min:1|max:65535',
                'smtp_username' => 'nullable|string|max:255',
                'smtp_password' => 'nullable|string|max:500',
                'ssh_host' => 'nullable|string|max:255',
                'ftp_ssh_user' => 'nullable|string|max:255',
                'ftp_ssh_password' => 'nullable|string|max:500',
                'hosting_provider' => 'nullable|string|max:100',
                'ssl_provider' => 'nullable|string|max:100',
                'dns_provider' => 'nullable|string|max:100',
            ]);
            $site->update($d);
            return back()->with('success', 'Settings saved.');
        })->name('sites.settings.update');
        Route::get('/sites/{site}/files', fn (Site $site) => Inertia::render('sites/files', ['site' => $site]))->name('sites.files');

        // Editor — kept as Blade (Phase 3 overhaul)
        Route::get('/sites/{site}/pages/{page}/edit', fn (Site $site, Page $page) => view('dashboard.editor.index', ['site' => $site, 'page' => $page]))->name('editor');

        // Editor preview
        Route::get('/preview/{site}/{page}', [EditorPreviewController::class, 'show'])->name('editor.preview');
        Route::get('/preview/{site}/asset/{path}', [EditorPreviewController::class, 'asset'])->where('path', '.*')->name('editor.asset');

        // SEO
        Route::get('/sites/{site}/pages/{page}/seo', fn (Site $site, Page $page) => Inertia::render('sites/seo-meta', ['site' => $site, 'page' => $page]))->name('seo.meta');
        Route::get('/sites/{site}/redirects', fn (Site $site) => Inertia::render('sites/redirects', ['site' => $site]))->name('seo.redirects');

        // Content
        Route::get('/sites/{site}/blog', fn (Site $site) => Inertia::render('sites/blog-index', ['site' => $site]))->name('blog.index');
        Route::get('/sites/{site}/blog/create', fn (Site $site) => Inertia::render('sites/blog-create', ['site' => $site]))->name('blog.create');
        Route::get('/sites/{site}/blog/{blogPost}/edit', function (Site $site, BlogPost $blogPost) {
            abort_unless($blogPost->site_id === $site->id, 404);

            return Inertia::render('sites/blog-edit', ['site' => $site, 'postId' => $blogPost->id]);
        })->name('blog.edit');
        Route::get('/sites/{site}/products', fn (Site $site) => Inertia::render('sites/products', ['site' => $site]))->name('products.index');
        Route::get('/sites/{site}/products/create', fn (Site $site) => Inertia::render('sites/product-create', ['site' => $site]))->name('products.create');
        Route::get('/sites/{site}/templates', fn (Site $site) => Inertia::render('sites/templates', ['site' => $site]))->name('templates.index');
    });

    // Analytics
    Route::get('/analytics', fn () => Inertia::render('analytics/index'))->name('analytics');

    // Email
    Route::get('/inbox', fn () => Inertia::render('email/inbox'))->name('inbox');
    Route::get('/subscribers', fn () => Inertia::render('email/subscribers'))->name('subscribers');
    Route::get('/newsletters', fn () => Inertia::render('email/campaigns'))->name('newsletters');

    // Settings
    Route::get('/settings', function () {
        $user = auth()->user();
        return Inertia::render('settings/index', [
            'twoFactorEnabled' => (bool) $user->two_factor_secret,
            'twoFactorConfirmed' => (bool) $user->two_factor_confirmed_at,
        ]);
    })->name('settings');
    Route::get('/system', fn () => Inertia::render('settings/system'))->name('system.diagnostics')->middleware('can:viewHorizon');
});
