<?php

use App\Http\Controllers\EditorPreviewController;
use App\Models\BlogPost;
use App\Models\Page;
use App\Models\Site;
use Illuminate\Support\Facades\Route;

// ── Guest ───────────────────────────────────────
Route::get('/', fn () => redirect()->route('login'));

// ── Dashboard (auth required) ───────────────────
Route::middleware(['auth'])->scopeBindings()->prefix('dashboard')->group(function () {

    Route::get('/', fn () => view('dashboard.index'))->name('dashboard');

    // Sites
    Route::get('/sites', fn () => view('dashboard.sites.index'))->name('sites.index');
    Route::get('/sites/create', fn () => view('dashboard.sites.create'))->name('sites.create');
    Route::get('/sites/{site}', function (Site $site) {
        $site->loadCount([
            'inboxMessages as inbox_unread_count' => fn ($q) => $q->where('direction', 'inbound')->where('is_read', false),
        ]);

        return view('dashboard.sites.show', ['site' => $site]);
    })->name('sites.show');
    Route::get('/sites/{site}/inbox', fn (Site $site) => view('dashboard.sites.inbox', ['site' => $site]))->name('sites.inbox');
    Route::get('/sites/{site}/settings', fn (Site $site) => view('dashboard.sites.settings', ['site' => $site]))->name('sites.settings');
    Route::get('/sites/{site}/files', fn (Site $site) => view('dashboard.sites.files', ['site' => $site]))->name('sites.files');

    // Editor
    Route::get('/sites/{site}/pages/{page}/edit', fn (Site $site, Page $page) => view('dashboard.editor.index', ['site' => $site, 'page' => $page]))->name('editor');

    // Editor preview
    Route::get('/preview/{site}/{page}', [EditorPreviewController::class, 'show'])->name('editor.preview');
    Route::get('/preview/{site}/asset/{path}', [EditorPreviewController::class, 'asset'])->where('path', '.*')->name('editor.asset');

    // SEO
    Route::get('/sites/{site}/pages/{page}/seo', fn (Site $site, Page $page) => view('dashboard.seo.meta', ['site' => $site, 'page' => $page]))->name('seo.meta');
    Route::get('/sites/{site}/redirects', fn (Site $site) => view('dashboard.seo.redirects', ['site' => $site]))->name('seo.redirects');

    // Content
    Route::get('/sites/{site}/blog', fn (Site $site) => view('dashboard.content.blog-index', ['site' => $site]))->name('blog.index');
    Route::get('/sites/{site}/blog/create', fn (Site $site) => view('dashboard.content.blog-create', ['site' => $site]))->name('blog.create');
    Route::get('/sites/{site}/blog/{blogPost}/edit', function (Site $site, BlogPost $blogPost) {
        abort_unless($blogPost->site_id === $site->id, 404);

        return view('dashboard.content.blog-edit', ['site' => $site, 'postId' => $blogPost->id]);
    })->name('blog.edit');
    Route::get('/sites/{site}/products', fn (Site $site) => view('dashboard.content.product-index', ['site' => $site]))->name('products.index');
    Route::get('/sites/{site}/products/create', fn (Site $site) => view('dashboard.content.product-create', ['site' => $site]))->name('products.create');
    Route::get('/sites/{site}/templates', fn (Site $site) => view('dashboard.content.templates', ['site' => $site]))->name('templates.index');

    // Analytics
    Route::get('/analytics', fn () => view('dashboard.analytics.index'))->name('analytics');

    // Email
    Route::get('/inbox', fn () => view('dashboard.email.inbox'))->name('inbox');
    Route::get('/subscribers', fn () => view('dashboard.email.subscribers'))->name('subscribers');
    Route::get('/newsletters', fn () => view('dashboard.email.campaigns'))->name('newsletters');

    // Settings
    Route::get('/settings', fn () => view('dashboard.settings.index'))->name('settings');
    Route::get('/system', fn () => view('dashboard.settings.system'))->name('system.diagnostics');
});
