<?php

use App\Http\Controllers\Api\FormSubmissionController;
use App\Http\Controllers\Api\InboxInboundController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\SiteController;
use App\Http\Controllers\TrackingController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

// ── Public (no auth) ────────────────────────────

// GitHub webhook receiver
Route::post('/webhooks/github', [WebhookController::class, 'github'])
    ->name('webhooks.github');
Route::post('/webhooks/github/{site}', [WebhookController::class, 'github'])
    ->name('webhooks.github.site');

Route::get('/tracking/{site}/pixelkraft.js', [TrackingController::class, 'script'])
    ->name('tracking.script');
Route::post('/tracking/{site}/collect', [TrackingController::class, 'collect'])
    ->middleware('throttle:120,1')
    ->name('tracking.collect');

// Contact form submission endpoint (rate limited, no auth)
Route::post('/forms/{slug}', [FormSubmissionController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('api.forms.store');

// Newsletter unsubscribe (signed URL, no auth)
Route::get('/unsubscribe/{subscriber}', function (\App\Models\NewsletterSubscriber $subscriber) {
    $subscriber->update(['status' => 'unsubscribed']);

    return response('<html><body style="font-family:system-ui;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;background:#f4f4f5;"><div style="text-align:center;"><h1>Unsubscribed</h1><p>You have been successfully unsubscribed.</p></div></body></html>', 200, [
        'Content-Type' => 'text/html',
    ]);
})->name('api.unsubscribe')->middleware('signed');

// Inbound project inbox (optional Bearer INBOX_INBOUND_SECRET)
Route::post('/inbox/{slug}', [InboxInboundController::class, 'store'])
    ->middleware('throttle:30,1')
    ->name('api.inbox.inbound');
// ── Authenticated API (Sanctum) — /api/v1/* ─────

Route::prefix('v1')->middleware(['auth:sanctum', 'site.access'])->group(function () {

    // Sites
    Route::get('/sites', [SiteController::class, 'index'])->name('api.v1.sites.index');
    Route::get('/sites/{site}', [SiteController::class, 'show'])->name('api.v1.sites.show');
    Route::post('/sites/{site}/sync', [SiteController::class, 'sync'])->name('api.v1.sites.sync');
    Route::post('/sites/{site}/deploy', [SiteController::class, 'deploy'])->name('api.v1.sites.deploy');
    Route::post('/sites/{site}/rollback/{logId}', [SiteController::class, 'rollback'])->name('api.v1.sites.rollback');
    Route::get('/sites/{site}/pages', [SiteController::class, 'pages'])->name('api.v1.sites.pages');
    Route::get('/sites/{site}/deploys', [SiteController::class, 'deploys'])->name('api.v1.sites.deploys');
    Route::get('/sites/{site}/analytics', [SiteController::class, 'analytics'])->name('api.v1.sites.analytics');
    Route::get('/sites/{site}/releases', [SiteController::class, 'releases'])->name('api.v1.sites.releases');
    Route::get('/sites/{site}/git-operations', [SiteController::class, 'gitOperations'])->name('api.v1.sites.git-operations');

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index'])->name('api.v1.notifications.index');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead'])->name('api.v1.notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('api.v1.notifications.readAll');
});
