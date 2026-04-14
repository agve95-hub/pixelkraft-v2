<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Models\Site;
use App\Models\UptimeCheck;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class CheckUptime extends Command
{
    protected $signature = 'pixelkraft:check-uptime';

    protected $description = 'Run uptime checks on all active sites';

    public function handle(): int
    {
        $sites = Site::where('is_active', true)
            ->whereNotNull('domain')
            ->where('deploy_status', 'live')
            ->get();

        foreach ($sites as $site) {
            $startTime = microtime(true);

            $degradedAfterMs = (int) config('pixelkraft.monitoring.uptime_degraded_after_ms', 3000);

            try {
                $response = Http::timeout(15)->get("https://{$site->domain}");
                $responseTimeMs = (int) ((microtime(true) - $startTime) * 1000);
                $isUp = $response->successful();
                $statusCode = $response->status();
            } catch (\Throwable $e) {
                $responseTimeMs = (int) ((microtime(true) - $startTime) * 1000);
                $isUp = false;
                $statusCode = 0;
            }

            $isDegraded = $isUp
                && $responseTimeMs >= $degradedAfterMs
                && $statusCode >= 200
                && $statusCode < 300;

            UptimeCheck::create([
                'site_id' => $site->id,
                'status_code' => $statusCode,
                'response_time_ms' => $responseTimeMs,
                'is_up' => $isUp,
                'is_degraded' => $isDegraded,
                'checked_at' => now(),
            ]);

            // Alert after 3 consecutive failures
            if (! $isUp) {
                $recentFailures = $site->uptimeChecks()
                    ->latest('checked_at')
                    ->limit(3)
                    ->get()
                    ->filter(fn ($c) => ! $c->is_up)
                    ->count();

                if ($recentFailures >= 3) {
                    Notification::createAlert(
                        type: 'uptime_down',
                        title: "Site down: {$site->name}",
                        body: "{$site->domain} has failed {$recentFailures} consecutive checks.",
                        siteId: $site->id,
                    );
                }
            }
        }

        $this->info("Checked {$sites->count()} sites.");

        return self::SUCCESS;
    }
}
