<?php

namespace App\Console\Commands;

use App\Models\AnalyticsEvent;
use App\Models\ContentRevision;
use App\Models\DeployLog;
use App\Models\EditSession;
use App\Models\GitOperation;
use App\Models\Notification;
use App\Models\UptimeCheck;
use Illuminate\Console\Command;

class PruneMonitoringData extends Command
{
    // IMPORTANT: option signatures use "= :" (space before colon) so Symfony does NOT
    // treat the description as the default value. "=   :" (extra spaces) would make the
    // spaces+description the default, which casts to (int)0 and breaks the validation.
    protected $signature = 'platform:prune-monitoring
                            {--uptime-days= : Days of uptime_checks to retain (default: 30)}
                            {--events-days= : Days of analytics_events to retain (default: 90)}
                            {--sessions-days= : Days of closed edit_sessions to retain (default: 60)}
                            {--revisions-days= : Days of content_revisions to retain (default: 90)}
                            {--git-ops-days= : Days of git_operations to retain (default: 60)}
                            {--notifications-days= : Days of read notifications to retain (default: 30)}
                            {--deploys-days= : Days of non-rollback deploy_logs to retain (default: 90)}
                            {--dry-run : Report row counts without deleting anything}';

    protected $description = 'Prune old monitoring, audit, and session rows to prevent unbounded table growth.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        // ?? (null-only fallback) is correct here because the fixed option signatures
        // return null (not a description string) when the option is not provided.
        // Explicitly passing --option=0 still reaches the $days < 1 validation below.
        $windows = [
            'uptime_checks' => (int) ($this->option('uptime-days') ?? config('platform.monitoring.uptime_retention_days', 30)),
            'analytics_events' => (int) ($this->option('events-days') ?? config('platform.monitoring.events_retention_days', 90)),
            'edit_sessions' => (int) ($this->option('sessions-days') ?? config('platform.monitoring.sessions_retention_days', 60)),
            'content_revisions' => (int) ($this->option('revisions-days') ?? config('platform.monitoring.revisions_retention_days', 90)),
            'git_operations' => (int) ($this->option('git-ops-days') ?? config('platform.monitoring.git_ops_retention_days', 60)),
            'notifications' => (int) ($this->option('notifications-days') ?? config('platform.monitoring.notifications_retention_days', 30)),
            'deploy_logs' => (int) ($this->option('deploys-days') ?? config('platform.monitoring.deploy_logs_retention_days', 90)),
        ];

        foreach ($windows as $table => $days) {
            if ($days < 1) {
                $this->error("Retention days for {$table} must be at least 1.");

                return self::FAILURE;
            }
        }

        $cutoff = fn (int $days) => now()->subDays($days);

        $this->prune(
            'uptime_checks',
            UptimeCheck::query()->where('checked_at', '<', $cutoff($windows['uptime_checks'])),
            $windows['uptime_checks'],
            $dryRun
        );

        $this->prune(
            'analytics_events',
            AnalyticsEvent::query()->where('created_at', '<', $cutoff($windows['analytics_events'])),
            $windows['analytics_events'],
            $dryRun
        );

        // Closed/conflicted edit sessions only — active sessions must never be pruned.
        $this->prune(
            'edit_sessions (closed/conflicted)',
            EditSession::query()
                ->whereIn('status', ['closed', 'conflicted', 'expired'])
                ->where('updated_at', '<', $cutoff($windows['edit_sessions'])),
            $windows['edit_sessions'],
            $dryRun
        );

        $this->prune(
            'content_revisions',
            ContentRevision::query()->where('created_at', '<', $cutoff($windows['content_revisions'])),
            $windows['content_revisions'],
            $dryRun
        );

        $this->prune(
            'git_operations',
            GitOperation::query()->where('started_at', '<', $cutoff($windows['git_operations'])),
            $windows['git_operations'],
            $dryRun
        );

        // Read notifications only — unread ones must stay visible.
        $this->prune(
            'notifications (read)',
            Notification::query()
                ->where('is_read', true)
                ->where('created_at', '<', $cutoff($windows['notifications'])),
            $windows['notifications'],
            $dryRun
        );

        // Keep rollback-eligible deploys (those with a snapshot_tag) regardless of age.
        $this->prune(
            'deploy_logs (no snapshot)',
            DeployLog::query()
                ->whereNull('snapshot_tag')
                ->where('created_at', '<', $cutoff($windows['deploy_logs'])),
            $windows['deploy_logs'],
            $dryRun
        );

        if ($dryRun) {
            $this->comment('Dry run complete — no rows were deleted.');
        }

        return self::SUCCESS;
    }

    private function prune(string $label, \Illuminate\Database\Eloquent\Builder $query, int $days, bool $dryRun): void
    {
        try {
            $count = $query->count();

            if ($dryRun) {
                $this->line("  [dry-run] {$label}: {$count} rows older than {$days} days would be deleted.");
            } else {
                $query->delete();
                $this->info("  Deleted {$count} {$label} rows older than {$days} days.");
            }
        } catch (\Throwable $e) {
            // Log and continue — a single table failure must not abort the whole run.
            $this->warn("  Could not prune {$label}: {$e->getMessage()}");
        }
    }
}
