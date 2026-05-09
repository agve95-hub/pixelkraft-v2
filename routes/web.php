<?php

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

// ── Health check (no auth) ───────────────────────
// Used by post-deploy CI smoke tests, load balancers, and external uptime monitors.
Route::get('/health', function () {
    $checks = [];
    $healthy = true;

    // Database connectivity
    try {
        DB::connection()->getPdo();
        $checks['database'] = 'ok';
    } catch (Throwable) {
        $checks['database'] = 'error';
        $healthy = false;
    }

    // Cache / Redis connectivity
    try {
        $key = 'platform:health:'.uniqid('', true);
        Cache::put($key, 1, 10);
        Cache::forget($key);
        $checks['cache'] = 'ok';
    } catch (Throwable) {
        $checks['cache'] = 'error';
        $healthy = false;
    }

    return response()->json([
        'status' => $healthy ? 'ok' : 'degraded',
        'checks' => $checks,
        'timestamp' => now()->toIso8601String(),
    ], $healthy ? 200 : 503);
})->withoutMiddleware([
    StartSession::class,
    ShareErrorsFromSession::class,
    PreventRequestForgery::class,
])->name('health');

// ── Guest ────────────────────────────────────────
Route::get('/', fn () => redirect()->route('login'));

// ── Dashboard (auth required) ────────────────────
Route::middleware(['auth'])->scopeBindings()->prefix('dashboard')->group(function () {
    require __DIR__.'/dashboard/main.php';
    require __DIR__.'/dashboard/sites.php';
    require __DIR__.'/dashboard/global.php';
});
