<?php

use App\Http\Controllers\Api\FormSubmissionController;
use App\Http\Controllers\Api\InboxInboundController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\SiteController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

// ── Public (no auth) ────────────────────────────

// GitHub webhook receiver
Route::post('/webhooks/github', [WebhookController::class, 'github'])
    ->name('webhooks.github');

// Contact form submission endpoint (rate limited, no auth)
Route::post('/forms/{slug}', [FormSubmissionController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('api.forms.store');

<<<<<<< HEAD
// Newsletter unsubscribe (signed URL, no auth)
Route::get('/unsubscribe/{subscriber}', function (\App\Models\NewsletterSubscriber $subscriber) {
    $subscriber->update(['status' => 'unsubscribed']);

    return response('<html><body style="font-family:system-ui;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;background:#f4f4f5;"><div style="text-align:center;"><h1>Unsubscribed</h1><p>You have been successfully unsubscribed.</p></div></body></html>', 200, [
        'Content-Type' => 'text/html',
    ]);
})->name('api.unsubscribe')->middleware('signed');
=======
// Inbound project inbox (optional Bearer INBOX_INBOUND_SECRET)
Route::post('/inbox/{slug}', [InboxInboundController::class, 'store'])
    ->middleware('throttle:30,1')
    ->name('api.inbox.inbound');
>>>>>>> 95308f6 (Email)

// ── Authenticated API (Sanctum) ─────────────────

Route::middleware('auth:sanctum')->group(function () {

    // Sites
    Route::get('/sites', [SiteController::class, 'index'])->name('api.sites.index');
    Route::get('/sites/{site}', [SiteController::class, 'show'])->name('api.sites.show');
    Route::post('/sites/{site}/sync', [SiteController::class, 'sync'])->name('api.sites.sync');
    Route::post('/sites/{site}/deploy', [SiteController::class, 'deploy'])->name('api.sites.deploy');
    Route::post('/sites/{site}/rollback/{logId}', [SiteController::class, 'rollback'])->name('api.sites.rollback');
    Route::get('/sites/{site}/pages', [SiteController::class, 'pages'])->name('api.sites.pages');
    Route::get('/sites/{site}/deploys', [SiteController::class, 'deploys'])->name('api.sites.deploys');

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index'])->name('api.notifications.index');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead'])->name('api.notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('api.notifications.readAll');
});
