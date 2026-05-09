<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Models\Site;
use App\Models\UptimeCheck;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class CheckUptime extends Command
{
    protected $signature = 'platform:check-uptime';

    protected $description = 'Run uptime checks on all active sites';

    public function handle(): int
    {
        $sites = Site::where('is_active', true)
            ->whereNotNull('domain')
            ->where('deploy_status', 'live')
            ->get();

        foreach ($sites as $site) {
            $startTime = microtime(true);

            $degradedAfterMs = (int) config('platform.monitoring.uptime_degraded_after_ms', 3000);

            // SSRF guard: resolve the domain to an IP and reject requests to
            // private / loopback / link-local ranges.  A user-configured domain
            // could point to an internal service via DNS (rebinding attack).
            $domain = (string) $site->domain;
            $resolvedIp = gethostbyname($domain);
            $isPublicIp = $resolvedIp !== $domain
                && filter_var(
                    $resolvedIp,
                    FILTER_VALIDATE_IP,
                    FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
                );

            if (! $isPublicIp) {
                // Record as "unknown" (status 0) rather than silently skipping so
                // the dashboard still shows a check was attempted.
                UptimeCheck::create([
                    'site_id' => $site->id,
                    'status_code' => 0,
                    'response_time_ms' => 0,
                    'is_up' => false,
                    'is_degraded' => false,
                    'checked_at' => now(),
                ]);

                continue;
            }

            try {
                $response = Http::timeout(15)->get("https://{$domain}");
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
