<?php

use Illuminate\Support\Facades\Route;

// Analytics (data fetched by UnifiedDashboard Livewire component)
Route::get('/analytics', fn () => view('dashboard.analytics.index'))->name('analytics');

// Email (data fetched by Livewire components)
Route::get('/inbox', fn () => view('dashboard.email.inbox'))->name('inbox');
Route::get('/subscribers', fn () => view('dashboard.email.subscribers'))->name('subscribers');
Route::get('/newsletters', fn () => view('dashboard.email.campaigns'))->name('newsletters');

// Settings
Route::get('/settings', function () {
    $user = auth()->user();

    return view('dashboard.settings.index', [
        'twoFactorEnabled' => (bool) $user->two_factor_secret,
        'twoFactorConfirmed' => (bool) $user->two_factor_confirmed_at,
    ]);
})->name('settings');
Route::get('/system', function () {
    $diagnostics = [
        ['label' => 'PHP version', 'value' => PHP_VERSION, 'status' => version_compare(PHP_VERSION, '8.2', '>=') ? 'ok' : 'warn'],
        ['label' => 'APP_ENV', 'value' => config('app.env'), 'status' => config('app.env') === 'production' ? 'ok' : 'warn'],
        ['label' => 'APP_DEBUG', 'value' => config('app.debug') ? 'true' : 'false', 'status' => config('app.debug') ? 'error' : 'ok'],
        ['label' => 'Queue driver', 'value' => config('queue.default'), 'status' => config('queue.default') !== 'sync' ? 'ok' : 'warn'],
        ['label' => 'Cache driver', 'value' => config('cache.default'), 'status' => 'ok'],
        ['label' => 'DB connection', 'value' => config('database.default'), 'status' => 'ok'],
    ];

    return view('dashboard.settings.system', ['diagnostics' => $diagnostics]);
})->name('system.diagnostics')->middleware('can:viewHorizon');
