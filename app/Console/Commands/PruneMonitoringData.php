<?php

namespace App\Console\Commands;

use App\Models\AnalyticsEvent;
use App\Models\UptimeCheck;
use Illuminate\Console\Command;

class PruneMonitoringData extends Command
{
    /**
     * Artisan signature.
     *
     * Without options the command uses the defaults defined in config/platform.php.
     * Override per-run with --uptime-days=N and/or --events-days=N.
     */
    protected $signature = 'platform:prune-monitoring
                            {--uptime-days= : Days of uptime_checks to retain (default: config value)}
                            {--events-days= : Days of analytics_events to retain (default: config value)}
                            {--dry-run      : Report row counts without deleting anything}';

    protected $description = 'Prune old uptime_checks and analytics_events rows to prevent unbounded table growth.';

    public function handle(): int
    {
        $uptimeDays = (int) ($this->option('uptime-days')
            ?? config('platform.monitoring.uptime_retention_days', 30));

        $eventsDays = (int) ($this->option('events-days')
            ?? config('platform.monitoring.events_retention_days', 90));

        $dryRun = (bool) $this->option('dry-run');

        if ($uptimeDays < 1 || $eventsDays < 1) {
            $this->error('Retention days must be at least 1.');

            return self::FAILURE;
        }

        $uptimeCutoff = now()->subDays($uptimeDays);
        $eventsCutoff = now()->subDays($eventsDays);

        // ── Uptime checks ─────────────────────────────────────────────────────
        $uptimeQuery = UptimeCheck::query()->where('checked_at', '<', $uptimeCutoff);
        $uptimeCount = $uptimeQuery->count();

        if ($dryRun) {
            $this->line("  [dry-run] uptime_checks: {$uptimeCount} rows older than {$uptimeDays} days would be deleted.");
        } else {
            $uptimeQuery->delete();
            $this->info("  Deleted {$uptimeCount} uptime_checks rows older than {$uptimeDays} days.");
        }

        // ── Analytics events ──────────────────────────────────────────────────
        // Raw events are aggregated nightly into analytics_snapshots; raw rows
        // older than the retention window are safe to delete.
        $eventsQuery = AnalyticsEvent::query()->where('created_at', '<', $eventsCutoff);
        $eventsCount = $eventsQuery->count();

        if ($dryRun) {
            $this->line("  [dry-run] analytics_events: {$eventsCount} rows older than {$eventsDays} days would be deleted.");
        } else {
            $eventsQuery->delete();
            $this->info("  Deleted {$eventsCount} analytics_events rows older than {$eventsDays} days.");
        }

        if ($dryRun) {
            $this->comment('Dry run complete — no rows were deleted.');
        }

        return self::SUCCESS;
    }
}
