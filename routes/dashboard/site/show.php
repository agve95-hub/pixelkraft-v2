<?php

use App\Models\AnalyticsSnapshot;
use App\Models\Notification;
use App\Models\Site;
use App\Models\SiteInboxMessage;
use App\Support\SeoIssueSummary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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

    return view('dashboard.sites.show', [
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
Route::get('/sites/{site}/inbox', fn (Site $site) => view('dashboard.sites.inbox', ['site' => $site]))->name('sites.inbox');
Route::post('/sites/{site}/inbox', function (Request $request, Site $site) {
    $d = $request->validate(['to_email' => 'required|email|max:255', 'subject' => 'required|string|max:255', 'body' => 'required|string']);
    $site->inboxMessages()->create(['direction' => 'outbound', 'user_id' => auth()->id(), 'to_email' => $d['to_email'], 'subject' => $d['subject'], 'body' => $d['body'], 'is_read' => true, 'source' => 'dashboard']);

    return back()->with('success', 'Message sent.');
})->name('sites.inbox.compose');
Route::post('/sites/{site}/inbox/{message}/archive', function (Site $site, SiteInboxMessage $message) {
    abort_unless($message->site_id === $site->id, 403);
    $message->update(['is_archived' => ! $message->is_archived]);

    return back();
})->withoutScopedBindings()->name('sites.inbox.archive');
