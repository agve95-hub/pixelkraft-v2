<?php

use App\Http\Controllers\Api\FormSubmissionController;
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
    ->name('api.forms.store');

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
