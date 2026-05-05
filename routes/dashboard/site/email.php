<?php

use App\Models\NewsletterCampaign;
use App\Models\NewsletterSubscriber;
use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Subscribers
Route::get('/sites/{site}/subscribers', fn (Site $site) => view('dashboard.email.subscribers', ['site' => $site]))->name('sites.subscribers');
Route::post('/sites/{site}/subscribers', function (Request $request, Site $site) {
    $d = $request->validate([
        'email' => 'required|email|max:255',
        'name' => 'nullable|string|max:255',
    ]);
    $site->newsletterSubscribers()->updateOrCreate(
        ['email' => $d['email']],
        ['name' => $d['name'] ?? null, 'status' => 'active'],
    );

    return back()->with('success', 'Subscriber added.');
})->name('sites.subscribers.store');
Route::delete('/sites/{site}/subscribers/{subscriber}', function (Site $site, NewsletterSubscriber $subscriber) {
    abort_unless($subscriber->site_id === $site->id, 403);
    $subscriber->delete();

    return back()->with('success', 'Subscriber removed.');
})->withoutScopedBindings()->name('sites.subscribers.destroy');
Route::post('/sites/{site}/subscribers/import', function (Request $request, Site $site) {
    $request->validate(['csv' => 'required|file|mimes:csv,txt|max:2048']);
    $handle = fopen($request->file('csv')->getRealPath(), 'r');
    $header = fgetcsv($handle);
    $emailIdx = array_search('email', array_map('strtolower', $header ?: []));
    $nameIdx = array_search('name', array_map('strtolower', $header ?: []));
    if ($emailIdx === false) {
        fclose($handle);

        return back()->withErrors(['csv' => 'CSV must have an "email" column.']);
    }
    $count = 0;
    while (($row = fgetcsv($handle)) !== false) {
        $email = trim($row[$emailIdx] ?? '');
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            continue;
        }
        $site->newsletterSubscribers()->updateOrCreate(
            ['email' => $email],
            ['name' => ($nameIdx !== false ? trim($row[$nameIdx] ?? '') : null) ?: null, 'status' => 'active'],
        );
        $count++;
    }
    fclose($handle);

    return back()->with('success', "Imported {$count} subscriber(s).");
})->name('sites.subscribers.import');

// Newsletter campaigns
Route::get('/sites/{site}/newsletters', fn (Site $site) => view('dashboard.email.campaigns', ['site' => $site]))->name('sites.newsletters');
Route::post('/sites/{site}/newsletters', function (Request $request, Site $site) {
    $d = $request->validate([
        'subject' => 'required|string|max:255',
        'body_html' => 'nullable|string',
        'scheduled_at' => 'nullable|date',
    ]);
    $status = ($d['scheduled_at'] ?? null) ? 'scheduled' : 'draft';
    $site->newsletterCampaigns()->create(array_merge($d, ['status' => $status]));

    return back()->with('success', 'Campaign created.');
})->name('sites.newsletters.store');
Route::put('/sites/{site}/newsletters/{campaign}', function (Request $request, Site $site, NewsletterCampaign $campaign) {
    abort_unless($campaign->site_id === $site->id, 403);
    abort_if($campaign->isSent(), 403);
    $d = $request->validate([
        'subject' => 'required|string|max:255',
        'body_html' => 'nullable|string',
        'scheduled_at' => 'nullable|date',
    ]);
    $status = ($d['scheduled_at'] ?? null) ? 'scheduled' : 'draft';
    $campaign->update(array_merge($d, ['status' => $status]));

    return back()->with('success', 'Campaign updated.');
})->withoutScopedBindings()->name('sites.newsletters.update');
Route::post('/sites/{site}/newsletters/{campaign}/send', function (Site $site, NewsletterCampaign $campaign) {
    abort_unless($campaign->site_id === $site->id, 403);
    abort_if($campaign->isSent(), 403);
    $campaign->update(['status' => 'sending', 'scheduled_at' => null]);

    return back()->with('success', 'Campaign queued for sending.');
})->withoutScopedBindings()->name('sites.newsletters.send');
Route::delete('/sites/{site}/newsletters/{campaign}', function (Site $site, NewsletterCampaign $campaign) {
    abort_unless($campaign->site_id === $site->id, 403);
    abort_if($campaign->isSent(), 403);
    $campaign->delete();

    return back()->with('success', 'Campaign deleted.');
})->withoutScopedBindings()->name('sites.newsletters.destroy');
