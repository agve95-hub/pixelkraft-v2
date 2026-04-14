<?php

namespace App\Console\Commands;

use App\Jobs\SyncFromWebhookJob;
use App\Models\Site;
use App\Models\WebhookDelivery;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class ReplayWebhooks extends Command
{
    protected $signature = 'pixelkraft:replay-webhooks
                            {--since= : ISO8601 or relative time (e.g. "2 hours ago") — required}
                            {--dry-run : List matching deliveries without dispatching jobs}';

    protected $description = 'Re-dispatch SyncFromWebhookJob for GitHub push deliveries that never left the "received" state (stalled).';

    public function handle(): int
    {
        $sinceRaw = $this->option('since');
        if (! is_string($sinceRaw) || trim($sinceRaw) === '') {
            $this->error('The --since option is required (e.g. --since="2 hours ago").');

            return self::FAILURE;
        }

        try {
            $since = Carbon::parse($sinceRaw);
        } catch (\Throwable) {
            $this->error('Could not parse --since value. Use a Carbon-parseable string.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        $query = WebhookDelivery::query()
            ->where('provider', 'github')
            ->where('event', 'push')
            ->where('status', 'received')
            ->whereNull('processed_at')
            ->where('received_at', '>=', $since);

        $deliveries = $query->orderBy('received_at')->get();

        if ($deliveries->isEmpty()) {
            $this->comment('No stalled push webhook deliveries found for the given window.');

            return self::SUCCESS;
        }

        $dispatched = 0;
        foreach ($deliveries as $delivery) {
            $repository = (string) ($delivery->repository ?? '');
            $normalized = Site::normalizeGithubRepository($repository);
            if ($normalized === '') {
                $this->warn("Skipping delivery {$delivery->delivery_id}: missing repository.");

                continue;
            }

            $sites = Site::query()
                ->where('is_active', true)
                ->get()
                ->filter(fn (Site $site) => $site->normalizedGithubRepository() === $normalized);

            if ($delivery->site_id) {
                $sites = $sites->where('id', $delivery->site_id)->values();
            }

            $payload = is_array($delivery->payload) ? $delivery->payload : [];
            $ref = (string) ($payload['ref'] ?? '');
            $branch = str_starts_with($ref, 'refs/heads/')
                ? substr($ref, strlen('refs/heads/'))
                : '';

            if ($branch === '') {
                $this->warn("Skipping delivery {$delivery->delivery_id}: no branch ref in stored payload.");

                continue;
            }

            foreach ($sites as $site) {
                $siteBranch = (string) $site->getAttribute('branch');
                if ($branch !== $siteBranch) {
                    continue;
                }

                $slug = (string) $site->getAttribute('slug');

                if ($dryRun) {
                    $this->line("  [dry-run] Would dispatch sync for site [{$slug}] delivery [{$delivery->delivery_id}].");
                    $dispatched++;

                    continue;
                }

                SyncFromWebhookJob::dispatch($site, $payload, $delivery->delivery_id);
                $dispatched++;
                $this->info("  Dispatched sync for site [{$slug}] delivery [{$delivery->delivery_id}].");
            }
        }

        if ($dryRun) {
            $this->comment("Dry run: {$dispatched} job(s) would be dispatched.");
        } else {
            $this->info("Dispatched {$dispatched} sync job(s).");
        }

        return self::SUCCESS;
    }
}
