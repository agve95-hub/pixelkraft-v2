<?php

use App\Enums\DeployStatus;
use App\Http\Controllers\Dashboard\SiteAnalyticsController;
use App\Http\Controllers\EditorPreviewController;
use App\Jobs\DeploySiteJob;
use App\Models\Page;
use App\Models\Redirect;
use App\Models\Site;
use App\Models\SiteInboxMessage;
use App\Rules\GitRemoteUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

Route::get('/sites/{site}/analytics', SiteAnalyticsController::class)->name('sites.analytics');
Route::get('/sites/{site}/maintenance', fn (Site $site) => view('dashboard.sites.maintenance', ['site' => $site]))->name('sites.maintenance');
Route::get('/sites/{site}/settings', fn (Site $site) => view('dashboard.sites.settings', ['site' => $site]))->name('sites.settings');
Route::put('/sites/{site}/settings', function (Request $request, Site $site) {
    $d = $request->validate([
        'name' => 'required|string|max:255',
        'domain' => 'nullable|string|max:255',
        'repo_url' => ['nullable', 'string', 'max:500', new GitRemoteUrl],
        'branch' => 'nullable|string|max:100',
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
Route::post('/sites/{site}/deploy', function (Site $site) {
    $site->update(['deploy_status' => DeployStatus::Deploying]);
    DeploySiteJob::dispatch($site, 'manual');

    return back()->with('success', 'Deployment started.');
})->name('sites.deploy');
Route::get('/sites/{site}/files', fn (Site $site) => view('dashboard.sites.files', ['site' => $site]))->name('sites.files');
Route::post('/sites/{site}/files', function (Request $request, Site $site) {
    $request->validate([
        'file' => [
            'required', 'file', 'max:20480',
            'mimes:jpg,jpeg,png,gif,webp,avif,svg,pdf,txt,csv,json,xml,zip,woff,woff2,ttf,otf,ico',
        ],
    ]);
    $file = $request->file('file');
    $original = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
    $ext = $file->getClientOriginalExtension();
    $safe = Str::slug($original) ?: 'file';
    $filename = $safe.'-'.substr(uniqid('', true), -6).($ext ? '.'.$ext : '');
    $file->storeAs('sites/'.$site->id, $filename, 'public');

    return back()->with('success', 'File uploaded.');
})->name('sites.files.upload');
Route::delete('/sites/{site}/files/{filename}', function (Site $site, string $filename) {
    if (str_contains($filename, '/') || str_contains($filename, '..')) {
        abort(422);
    }
    Storage::disk('public')->delete('sites/'.$site->id.'/'.$filename);

    return back()->with('success', 'File deleted.');
})->name('sites.files.destroy');
Route::get('/sites/{site}/pages', fn (Site $site) => view('dashboard.sites.pages', ['site' => $site]))->name('sites.pages');

// Editor ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â kept as Blade (Phase 3 overhaul)
Route::get('/sites/{site}/pages/{page}/edit', fn (Site $site, Page $page) => view('dashboard.editor.index', ['site' => $site, 'page' => $page]))->name('editor');

// Editor preview
Route::get('/preview/{site}/{page}', [EditorPreviewController::class, 'show'])->name('editor.preview');
Route::get('/preview/{site}/asset/{path}', [EditorPreviewController::class, 'asset'])->where('path', '.*')->name('editor.asset');

// SEO
Route::get('/sites/{site}/pages/{page}/seo', fn (Site $site, Page $page) => view('dashboard.seo.meta', ['site' => $site, 'page' => $page]))->name('seo.meta');
Route::get('/sites/{site}/redirects', fn (Site $site) => view('dashboard.seo.redirects', ['site' => $site]))->name('seo.redirects');
Route::post('/sites/{site}/redirects', function (Request $request, Site $site) {
    $d = $request->validate([
        'from_path' => ['required', 'string', 'max:500', 'starts_with:/', 'not_regex:/[\r\n\t;{}]/'],
        'to_path' => ['required', 'string', 'max:500', 'not_regex:/[\r\n\t;{}]/'],
        'status_code' => ['nullable', 'integer', 'in:301,302'],
    ]);
    $site->redirects()->create(['from_path' => $d['from_path'], 'to_path' => $d['to_path'], 'status_code' => $d['status_code'] ?? 301]);

    return back();
})->name('seo.redirects.store');
Route::post('/sites/{site}/redirects/{redirect}/toggle', function (Site $site, Redirect $redirect) {
    abort_unless($redirect->site_id === $site->id, 403);
    $redirect->update(['is_active' => ! $redirect->is_active]);

    return back();
})->name('seo.redirects.toggle');
Route::delete('/sites/{site}/redirects/{redirect}', function (Site $site, Redirect $redirect) {
    abort_unless($redirect->site_id === $site->id, 403);
    $redirect->delete();

    return back();
})->name('seo.redirects.destroy');
Route::put('/sites/{site}/pages/{page}/seo', function (Request $request, Site $site, Page $page) {
    abort_unless($page->site_id === $site->id, 403);
    $page->update($request->validate(['title' => 'nullable|string|max:255', 'meta_description' => 'nullable|string|max:500', 'og_title' => 'nullable|string|max:255', 'og_description' => 'nullable|string|max:500', 'og_image' => 'nullable|url|max:500', 'canonical_url' => 'nullable|url|max:500']));

    return back()->with('success', 'SEO meta saved.');
})->name('seo.meta.update');
Route::put('/sites/{site}/maintenance', function (Request $request, Site $site) {
    $d = $request->validate(['enabled' => 'boolean', 'title' => 'nullable|string|max:255', 'message' => 'nullable|string|max:2000', 'allowed_ips' => 'nullable|string']);
    $ips = array_filter(array_map('trim', explode("\n", $d['allowed_ips'] ?? '')));
    $site->update(['maintenance_settings' => ['enabled' => $request->boolean('enabled'), 'title' => $d['title'] ?? null, 'message' => $d['message'] ?? null, 'allowed_ips' => array_values($ips)]]);

    return back()->with('success', 'Maintenance settings saved.');
})->name('sites.maintenance.update');
Route::get('/sites/{site}/maintenance/preview', function (Site $site) {
    $s = $site->maintenance_settings ?? [];
    $title = e($s['title'] ?? "We'll be back soon");
    $message = e($s['message'] ?? "We're performing scheduled maintenance. Please check back later.");

    return response("<!DOCTYPE html><html lang=\"en\"><head><meta charset=\"UTF-8\"><meta name=\"viewport\" content=\"width=device-width,initial-scale=1\"><title>{$title}</title><style>*{box-sizing:border-box;margin:0;padding:0}body{background:#0a0a0a;color:#e4e4e7;font-family:system-ui,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:2rem}main{max-width:480px;text-align:center}h1{font-size:1.5rem;font-weight:600;margin-bottom:1rem}p{color:#a1a1aa;line-height:1.6}.badge{display:inline-block;background:#27272a;border:1px solid #3f3f46;border-radius:9999px;font-size:0.75rem;padding:0.25rem 0.75rem;margin-bottom:1.5rem;color:#71717a}</style></head><body><main><span class=\"badge\">Maintenance preview ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â {$site->name}</span><h1>{$title}</h1><p>{$message}</p></main></body></html>", 200, ['Content-Type' => 'text/html']);
})->name('sites.maintenance.preview');
Route::post('/sites/{site}/inbox/{message}/read', function (Site $site, SiteInboxMessage $message) {
    abort_unless($message->site_id === $site->id, 403);
    $message->update(['is_read' => true]);

    return back();
})->withoutScopedBindings()->name('sites.inbox.read');
Route::delete('/sites/{site}/inbox/{message}', function (Site $site, SiteInboxMessage $message) {
    abort_unless($message->site_id === $site->id, 403);
    $message->delete();

    return back();
})->withoutScopedBindings()->name('sites.inbox.destroy');
