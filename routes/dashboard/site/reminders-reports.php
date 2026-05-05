<?php

use App\Models\Reminder;
use App\Models\Report;
use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/sites/{site}/reminders', fn (Site $site) => view('dashboard.sites.reminders', ['site' => $site]))->name('sites.reminders');
Route::post('/sites/{site}/reminders', function (Request $request, Site $site) {
    $d = $request->validate(['title' => 'required|string|max:255', 'due_date' => 'nullable|date', 'notes' => 'nullable|string|max:2000']);
    $site->reminders()->create($d);

    return back();
})->name('sites.reminders.store');
Route::put('/sites/{site}/reminders/{reminder}', function (Request $request, Site $site, Reminder $reminder) {
    abort_unless($reminder->site_id === $site->id, 403);
    $d = $request->validate(['title' => 'required|string|max:255', 'due_date' => 'nullable|date', 'notes' => 'nullable|string|max:2000']);
    $reminder->update($d);

    return back();
})->name('sites.reminders.update');
Route::post('/sites/{site}/reminders/{reminder}/complete', function (Site $site, Reminder $reminder) {
    abort_unless($reminder->site_id === $site->id, 403);
    $reminder->update(['is_done' => ! $reminder->is_done]);

    return back();
})->name('sites.reminders.complete');
Route::delete('/sites/{site}/reminders/{reminder}', function (Site $site, Reminder $reminder) {
    abort_unless($reminder->site_id === $site->id, 403);
    $reminder->delete();

    return back();
})->name('sites.reminders.destroy');
Route::get('/sites/{site}/reports', fn (Site $site) => view('dashboard.sites.reports', ['site' => $site]))->name('sites.reports');
Route::post('/sites/{site}/reports', function (Request $request, Site $site) {
    $d = $request->validate(['title' => 'required|string|max:255', 'report_date' => 'required|date', 'summary' => 'nullable|string', 'meta.visitors' => 'nullable|integer|min:0', 'meta.pageviews' => 'nullable|integer|min:0', 'meta.uptime_percent' => 'nullable|numeric|min:0|max:100', 'meta.work_done' => 'nullable|string', 'meta.issues' => 'nullable|string', 'meta.next_steps' => 'nullable|string']);
    $site->reports()->create(['title' => $d['title'], 'report_date' => $d['report_date'], 'summary' => $d['summary'] ?? null, 'meta' => $d['meta'] ?? null]);

    return back();
})->name('sites.reports.store');
Route::put('/sites/{site}/reports/{report}', function (Request $request, Site $site, Report $report) {
    abort_unless($report->site_id === $site->id, 403);
    $d = $request->validate(['title' => 'required|string|max:255', 'report_date' => 'required|date', 'summary' => 'nullable|string', 'meta.visitors' => 'nullable|integer|min:0', 'meta.pageviews' => 'nullable|integer|min:0', 'meta.uptime_percent' => 'nullable|numeric|min:0|max:100', 'meta.work_done' => 'nullable|string', 'meta.issues' => 'nullable|string', 'meta.next_steps' => 'nullable|string']);
    $report->update(['title' => $d['title'], 'report_date' => $d['report_date'], 'summary' => $d['summary'] ?? null, 'meta' => $d['meta'] ?? null]);

    return back();
})->name('sites.reports.update');
Route::post('/sites/{site}/reports/{report}/duplicate', function (Site $site, Report $report) {
    abort_unless($report->site_id === $site->id, 403);
    $new = $report->replicate();
    $new->title = $report->title.' (copy)';
    $new->save();

    return back();
})->name('sites.reports.duplicate');
Route::delete('/sites/{site}/reports/{report}', function (Site $site, Report $report) {
    abort_unless($report->site_id === $site->id, 403);
    $report->delete();

    return back();
})->name('sites.reports.destroy');
