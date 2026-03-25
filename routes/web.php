<?php

use Illuminate\Support\Facades\Route;

// ── Guest ───────────────────────────────────────
Route::get('/', fn () => redirect()->route('login'));

// ── Dashboard (auth required) ───────────────────
Route::middleware(['auth'])->prefix('dashboard')->group(function () {

    Route::get('/', fn () => view('dashboard.index'))->name('dashboard');

    // Sites
    Route::get('/sites', fn () => view('dashboard.sites.index'))->name('sites.index');
    Route::get('/sites/create', fn () => view('dashboard.sites.create'))->name('sites.create');
    Route::get('/sites/{site}', fn () => view('dashboard.sites.show'))->name('sites.show');
    Route::get('/sites/{site}/settings', fn () => view('dashboard.sites.settings'))->name('sites.settings');

    // Editor
    Route::get('/sites/{site}/pages/{page}/edit', fn () => view('dashboard.editor.index'))->name('editor');

    // Settings
    Route::get('/settings', fn () => view('dashboard.settings.index'))->name('settings');
});
