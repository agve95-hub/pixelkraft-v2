<?php

namespace App\Console\Commands;

use App\Models\WebhookDelivery;
use Illuminate\Console\Command;

class PruneWebhooks extends Command
{
    protected $signature = 'pixelkraft:prune-webhooks
                            {--days= : Delete deliveries older than this many days (default: config pixelkraft.monitoring.webhook_deliveries_retention_days)}
                            {--dry-run : Report how many rows would be deleted without deleting}';

    protected $description = 'Delete old webhook_deliveries rows to cap table growth and retention.';

    public function handle(): int
    {
        $opt = $this->option('days');
        $days = ($opt !== null && $opt !== '')
            ? max(1, (int) $opt)
            : max(1, (int) config('pixelkraft.monitoring.webhook_deliveries_retention_days', 30));
        $dryRun = (bool) $this->option('dry-run');

        $cutoff = now()->subDays($days);
        $query = WebhookDelivery::query()->where('received_at', '<', $cutoff);
        $count = $query->count();

        if ($dryRun) {
            $this->line("[dry-run] {$count} webhook_deliveries rows older than {$days} days would be deleted.");

            return self::SUCCESS;
        }

        $deleted = $query->delete();
        $this->info("Deleted {$deleted} webhook_deliveries rows older than {$days} days.");

        return self::SUCCESS;
    }
}
