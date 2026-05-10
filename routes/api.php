<?php

use App\Http\Controllers\Api\ActiveCampaignsController;
use App\Http\Controllers\Api\FormSubmissionController;
use App\Http\Controllers\Api\InboxInboundController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\SiteController;
use App\Http\Controllers\TrackingController;
use App\Http\Controllers\Api\ResendWebhookController;
use App\Http\Controllers\WebhookController;
use App\Models\NewsletterSubscriber;
use Illuminate\Support\Facades\Route;

// ── Public (no auth) ────────────────────────────

// GitHub webhook receiver
// Rate limited to 120/min per IP — well above GitHub's realistic delivery rate but
// prevents blind flood attacks before the HMAC signature check runs.
// Resend email lifecycle webhook (bounce, complaint, open, click)
Route::post('/webhooks/resend', [ResendWebhookController::class, 'handle'])
    ->middleware('throttle:300,1')
    ->name('webhooks.resend');

Route::post('/webhooks/github', [WebhookController::class, 'github'])
    ->middleware('throttle:120,1')
    ->name('webhooks.github');
Route::post('/webhooks/github/{site}', [WebhookController::class, 'github'])
    ->middleware('throttle:120,1')
    ->name('webhooks.github.site');

Route::get('/tracking/{site}/platform.js', [TrackingController::class, 'script'])
    ->name('tracking.script');
Route::post('/tracking/{site}/collect', [TrackingController::class, 'collect'])
    ->middleware('throttle:120,1')
    ->name('tracking.collect');

// Contact form submission endpoint (rate limited, no auth)
Route::post('/forms/{slug}', [FormSubmissionController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('api.forms.store');

// Newsletter unsubscribe (signed URL, no auth)
Route::get('/unsubscribe/{subscriber}', function (NewsletterSubscriber $subscriber) {
    $subscriber->update(['status' => 'unsubscribed']);

    return response('<html><head><style>body{align-items:center;background:#f4f4f5;display:flex;font-family:system-ui;height:100vh;justify-content:center;margin:0}.message{text-align:center}</style></head><body><div class="message"><h1>Unsubscribed</h1><p>You have been successfully unsubscribed.</p></div></body></html>', 200, [
        'Content-Type' => 'text/html',
    ]);
})->name('api.unsubscribe')->middleware('signed');

// Inbound project inbox (optional Bearer INBOX_INBOUND_SECRET)
Route::post('/inbox/{slug}', [InboxInboundController::class, 'store'])
    ->middleware('throttle:30,1')
    ->name('api.inbox.inbound');

// Active campaigns + announcements for a managed site (consumed by client-side JS)
// Public, cached 60s server-side, rate-limited to 60/min per IP.
Route::get('/sites/{site}/active-campaigns', ActiveCampaignsController::class)
    ->middleware('throttle:60,1')
    ->name('api.sites.active-campaigns');
// ── Authenticated API (Sanctum) — /api/v1/* ─────

Route::prefix('v1')->middleware(['auth:sanctum', 'sanctum.token.can', 'site.access'])->group(function () {

    // Sites
    Route::get('/sites', [SiteController::class, 'index'])->name('api.v1.sites.index');
    Route::get('/sites/{site}', [SiteController::class, 'show'])->name('api.v1.sites.show');
    // Sync: 10 per minute per user (prevents webhook-style hammering via API token)
    Route::post('/sites/{site}/sync', [SiteController::class, 'sync'])
        ->middleware('throttle:10,1')
        ->name('api.v1.sites.sync');
    // Deploy: 5 per minute per user (each dispatch queues a full build chain)
    Route::post('/sites/{site}/deploy', [SiteController::class, 'deploy'])
        ->middleware('throttle:5,1')
        ->name('api.v1.sites.deploy');
    // Rollback: 5 per minute per user
    Route::post('/sites/{site}/rollback/{logId}', [SiteController::class, 'rollback'])
        ->middleware('throttle:5,1')
        ->name('api.v1.sites.rollback');
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
