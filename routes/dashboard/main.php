<?php

use App\Enums\DeployStatus;
use App\Jobs\DeploySiteJob;
use App\Jobs\ImportSiteFromZipJob;
use App\Models\AnalyticsSnapshot;
use App\Models\FormSubmission;
use App\Models\Notification;
use App\Models\Page;
use App\Models\Site;
use App\Models\UptimeCheck;
use App\Rules\GitRemoteUrl;
use App\Support\SchemaState;
use App\Support\SeoIssueSummary;
use App\Support\SiteAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

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

    return view('dashboard.index', [
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
        'plotW' => $plotW,
        'plotH' => $plotH,
        'lineD' => $lineD,
        'areaD' => $areaD,
        'siteInsights' => $siteInsights,
        'sitesDown' => $sitesDown,
        'activeSites' => $activeSites,
    ]);
})->name('dashboard');

// Sites
Route::get('/sites', fn () => view('dashboard.sites.index'))->name('sites.index');
Route::get('/sites/create', fn () => view('dashboard.sites.create'))->name('sites.create');
Route::post('/sites', function (Request $request) {
    $validated = $request->validate([
        'name' => 'required|string|max:255',
        'project_type' => 'required|string',
        'source_type' => 'required|string|in:github,upload',
        'repo_url' => ['required_if:source_type,github', 'nullable', 'string', 'max:500', new GitRemoteUrl],
        'branch' => 'nullable|string|max:100',
        'build_command' => 'nullable|string|max:500',
        'github_token' => 'nullable|string|max:500',
        'domain' => 'nullable|string|max:253',
        'ssl_provider' => 'nullable|string',
        'client_first_name' => 'nullable|string|max:255',
        'client_last_name' => 'nullable|string|max:255',
        'client_email' => 'nullable|email|max:255',
        'client_company' => 'nullable|string|max:255',
    ]);

    // Upload source: site record is created here, ZIP is uploaded in a separate request

    $baseSlug = Str::slug($validated['name']);
    $slug = $baseSlug;
    $i = 2;
    while (Site::where('slug', $slug)->exists()) {
        $slug = $baseSlug.'-'.$i++;
    }

    $site = Site::create([
        'user_id' => auth()->id(),
        'name' => $validated['name'],
        'slug' => $slug,
        'project_type' => $validated['project_type'],
        'source_type' => $validated['source_type'],
        'repo_url' => $validated['repo_url'] ?? null,
        'branch' => $validated['branch'] ?? 'main',
        'build_command' => $validated['build_command'] ?? null,
        'github_token' => $validated['github_token'] ?? null,
        'domain' => $validated['domain'] ?? null,
        'ssl_provider' => $validated['ssl_provider'] ?? 'letsencrypt',
        'client_first_name' => $validated['client_first_name'] ?? null,
        'client_last_name' => $validated['client_last_name'] ?? null,
        'client_email' => $validated['client_email'] ?? null,
        'client_company' => $validated['client_company'] ?? null,
        'deploy_status' => DeployStatus::Queued,
    ]);

    if ($validated['source_type'] === 'github' && ! empty($validated['repo_url'])) {
        DeploySiteJob::dispatch($site, 'wizard');
    }

    return response()->json(['siteId' => $site->id]);
})->name('sites.store');
Route::delete('/sites/{siteId}', function (string $siteId) {
    $site = SiteAccess::findOrFail($siteId);
    $site->delete();

    return response()->json(['deleted' => true]);
})->name('sites.destroy');

Route::get('/sites/{siteId}/import-status', function (string $siteId) {
    $site = SiteAccess::findOrFail($siteId);

    return response()->json([
        'status' => $site->deploy_status,
        'lastDeployedAt' => $site->last_deployed_at,
        'deployLog' => $site->latestDeploy?->only('id', 'status', 'steps'),
    ]);
})->name('sites.import-status');

// ZIP upload Ã¢â‚¬â€ called after site creation when source_type=upload
Route::post('/sites/{siteId}/import/zip', function (Request $request, string $siteId) {
    $site = SiteAccess::findOrFail($siteId);

    $request->validate([
        'file' => [
            'required',
            'file',
            'mimes:zip',
            'max:204800', // 200 MB in kilobytes
        ],
    ]);

    $zipPath = $request->file('file')->store('imports/zips');

    if (! $zipPath) {
        return response()->json(['message' => 'Failed to store uploaded file.'], 500);
    }

    ImportSiteFromZipJob::dispatch($site, $zipPath);

    return response()->json([
        'status' => 'queued',
        'siteId' => $site->id,
        'message' => 'ZIP queued for import. Poll /import-status for progress.',
    ]);
})->name('sites.import.zip');
