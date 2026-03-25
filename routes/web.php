<?php

use App\Http\Controllers\EditorPreviewController;
use App\Models\Page;
use App\Models\Site;
use Illuminate\Support\Facades\Route;

// ── Guest ───────────────────────────────────────
Route::get('/', fn () => redirect()->route('login'));

// ── Dashboard (auth required) ───────────────────
Route::middleware(['auth'])->prefix('dashboard')->group(function () {

    Route::get('/', fn () => view('dashboard.index'))->name('dashboard');

    // Sites
    Route::get('/sites', fn () => view('dashboard.sites.index'))->name('sites.index');
    Route::get('/sites/create', fn () => view('dashboard.sites.create'))->name('sites.create');
    Route::get('/sites/{site}', fn (Site $site) => view('dashboard.sites.show', ['site' => $site]))->name('sites.show');
    Route::get('/sites/{site}/settings', fn (Site $site) => view('dashboard.sites.settings', ['site' => $site]))->name('sites.settings');

    // Editor
    Route::get('/sites/{site}/pages/{page}/edit', fn (Site $site, Page $page) => view('dashboard.editor.index', ['site' => $site, 'page' => $page]))->name('editor');

    // Editor preview (serves page HTML in iframe)
    Route::get('/preview/{site}/{page}', [EditorPreviewController::class, 'show'])->name('editor.preview');
    Route::get('/preview/{site}/asset/{path}', [EditorPreviewController::class, 'asset'])->where('path', '.*')->name('editor.asset');

    // SEO
    Route::get('/sites/{site}/pages/{page}/seo', fn (Site $site, Page $page) => view('dashboard.seo.meta', ['site' => $site, 'page' => $page]))->name('seo.meta');
    Route::get('/sites/{site}/redirects', fn (Site $site) => view('dashboard.seo.redirects', ['site' => $site]))->name('seo.redirects');

    // Content
    Route::get('/sites/{site}/blog', fn (Site $site) => view('dashboard.content.blog-index', ['site' => $site]))->name('blog.index');
    Route::get('/sites/{site}/blog/create', fn (Site $site) => view('dashboard.content.blog-create', ['site' => $site]))->name('blog.create');
    Route::get('/sites/{site}/blog/{post}/edit', fn (Site $site, $post) => view('dashboard.content.blog-edit', ['site' => $site, 'postId' => $post]))->name('blog.edit');
    Route::get('/sites/{site}/products', fn (Site $site) => view('dashboard.content.product-index', ['site' => $site]))->name('products.index');
    Route::get('/sites/{site}/products/create', fn (Site $site) => view('dashboard.content.product-create', ['site' => $site]))->name('products.create');
    Route::get('/sites/{site}/templates', fn (Site $site) => view('dashboard.content.templates', ['site' => $site]))->name('templates.index');

    // Analytics
    Route::get('/analytics', fn () => view('dashboard.analytics.index'))->name('analytics');

    // Settings
    Route::get('/settings', fn () => view('dashboard.settings.index'))->name('settings');
});
