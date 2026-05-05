<?php

use App\Models\Campaign;
use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/sites/{site}/campaigns', fn (Site $site) => view('dashboard.sites.campaigns', ['site' => $site]))->name('sites.campaigns');
Route::post('/sites/{site}/campaigns', function (Request $request, Site $site) {
    $d = $request->validate(['name' => 'required|string|max:255', 'headline' => 'nullable|string|max:255', 'body' => 'nullable|string', 'cta_text' => 'nullable|string|max:100', 'cta_url' => 'nullable|url|max:500', 'trigger' => 'nullable|in:on_load,on_scroll,on_exit,on_delay', 'starts_at' => 'nullable|date', 'ends_at' => 'nullable|date|after_or_equal:starts_at', 'priority' => 'nullable|integer', 'is_dismissible' => 'boolean', 'locale' => 'nullable|string|max:10']);
    $site->campaigns()->create(array_merge($d, ['trigger' => $d['trigger'] ?? 'on_load', 'priority' => $d['priority'] ?? 0, 'locale' => $d['locale'] ?? 'en', 'is_enabled' => false]));

    return back();
})->name('sites.campaigns.store');
Route::put('/sites/{site}/campaigns/{campaign}', function (Request $request, Site $site, Campaign $campaign) {
    abort_unless($campaign->site_id === $site->id, 403);
    $d = $request->validate(['name' => 'required|string|max:255', 'headline' => 'nullable|string|max:255', 'body' => 'nullable|string', 'cta_text' => 'nullable|string|max:100', 'cta_url' => 'nullable|url|max:500', 'trigger' => 'nullable|in:on_load,on_scroll,on_exit,on_delay', 'starts_at' => 'nullable|date', 'ends_at' => 'nullable|date|after_or_equal:starts_at', 'priority' => 'nullable|integer', 'is_dismissible' => 'boolean', 'locale' => 'nullable|string|max:10']);
    $campaign->update(array_merge($d, ['trigger' => $d['trigger'] ?? $campaign->trigger ?? 'on_load']));

    return back();
})->name('sites.campaigns.update');
Route::post('/sites/{site}/campaigns/{campaign}/toggle', function (Site $site, Campaign $campaign) {
    abort_unless($campaign->site_id === $site->id, 403);
    $campaign->update(['is_enabled' => ! $campaign->is_enabled]);

    return back();
})->name('sites.campaigns.toggle');
Route::post('/sites/{site}/campaigns/{campaign}/duplicate', function (Site $site, Campaign $campaign) {
    abort_unless($campaign->site_id === $site->id, 403);
    $new = $campaign->replicate();
    $new->name = $campaign->name.' (copy)';
    $new->is_enabled = false;
    $new->save();

    return back();
})->name('sites.campaigns.duplicate');
Route::delete('/sites/{site}/campaigns/{campaign}', function (Site $site, Campaign $campaign) {
    abort_unless($campaign->site_id === $site->id, 403);
    $campaign->delete();

    return back();
})->name('sites.campaigns.destroy');
