<?php

use Illuminate\Support\Facades\Schedule;

// ── Monitoring ──────────────────────────────────

// Uptime checks every 5 minutes
Schedule::command('platform:check-uptime')
    ->everyFiveMinutes()
    ->withoutOverlapping();

// Lighthouse audits weekly (Sunday 3am)
Schedule::command('platform:run-lighthouse')
    ->weeklyOn(0, '03:00')
    ->withoutOverlapping();

// Broken link crawler weekly (Sunday 5am)
Schedule::command('platform:crawl-links')
    ->weeklyOn(0, '05:00')
    ->withoutOverlapping();

// ── Analytics Sync ──────────────────────────────

// Sync GA + Cloudflare analytics daily at 6am
Schedule::command('platform:sync-analytics')
    ->dailyAt('06:00')
    ->withoutOverlapping();

// SEO analyzer: refresh scores and seo_issues for all active sites (after analytics)
Schedule::command('platform:analyze-seo --all')
    ->dailyAt('06:30')
    ->withoutOverlapping();

// ── SSL Monitoring ──────────────────────────────

// Check SSL expiry weekly (Monday 8am)
Schedule::command('platform:check-ssl')
    ->weeklyOn(1, '08:00');

// ── Backups ─────────────────────────────────────

// Full database backup daily at 2am via spatie/laravel-backup.
Schedule::command('backup:run --only-db')
    ->dailyAt('02:00')
    ->withoutOverlapping();

// Clean up old backups according to the retention policy in config/backup.php.
Schedule::command('backup:clean')
    ->dailyAt('02:30')
    ->withoutOverlapping();

// ── Content ─────────────────────────────────────

// Publish scheduled blog posts every minute
Schedule::command('platform:publish-scheduled')
    ->everyMinute()
    ->withoutOverlapping();

// Send scheduled newsletter campaigns every minute
Schedule::command('platform:send-campaigns')
    ->everyMinute()
    ->withoutOverlapping();

// ── Data retention ──────────────────────────────

// Prune old uptime_checks and analytics_events rows weekly (Sunday 1am).
// Defaults: 30 days for uptime samples, 90 days for raw analytics events.
Schedule::command('platform:prune-monitoring')
    ->weeklyOn(0, '01:00')
    ->withoutOverlapping();

// Prune webhook delivery audit rows weekly (Sunday 1:15am).
Schedule::command(
    'platform:prune-webhooks --days='.(int) config('platform.monitoring.webhook_deliveries_retention_days', 30)
)
    ->weeklyOn(0, '01:15')
    ->withoutOverlapping();

// ── Horizon ─────────────────────────────────────

Schedule::command('horizon:snapshot')
    ->everyFiveMinutes();
