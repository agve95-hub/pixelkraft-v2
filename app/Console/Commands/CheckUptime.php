<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Models\Site;
use App\Models\UptimeCheck;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class CheckUptime extends Command
{
    protected $signature = 'platform:check-uptime';

    protected $description = 'Run uptime checks on all active sites';

    public function handle(): int
    {
        $checked = 0;

        Site::where('is_active', true)
            ->whereNotNull('domain')
            ->where('deploy_status', 'live')
            ->chunkById(50, function ($sites) use (&$checked) {
                foreach ($sites as $site) {
                    $this->checkSite($site);
                    $checked++;
                }
            });

        $this->info("Checked {$checked} sites.");

        return self::SUCCESS;
    }

    private function checkSite(Site $site): void
    {
        $startTime = microtime(true);
        $degradedAfterMs = (int) config('platform.monitoring.uptime_degraded_after_ms', 3000);
        $domain = (string) $site->domain;

        // SSRF guard: reject requests to private/loopback/link-local IPs.
        $resolvedIp = gethostbyname($domain);
        $isPublicIp = $resolvedIp !== $domain
            && filter_var($resolvedIp, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);

        if (! $isPublicIp) {
            UptimeCheck::create([
                'site_id' => $site->id,
                'status_code' => 0,
                'response_time_ms' => 0,
                'is_up' => false,
                'is_degraded' => false,
                'checked_at' => now(),
            ]);

            return;
        }

        try {
            $response = Http::timeout(15)->get("https://{$domain}");
            $responseTimeMs = (int) ((microtime(true) - $startTime) * 1000);
            $isUp = $response->successful();
            $statusCode = $response->status();
        } catch (\Throwable) {
            $responseTimeMs = (int) ((microtime(true) - $startTime) * 1000);
            $isUp = false;
            $statusCode = 0;
        }

        $isDegraded = $isUp && $responseTimeMs >= $degradedAfterMs && $statusCode >= 200 && $statusCode < 300;

        UptimeCheck::create([
            'site_id' => $site->id,
            'status_code' => $statusCode,
            'response_time_ms' => $responseTimeMs,
            'is_up' => $isUp,
            'is_degraded' => $isDegraded,
            'checked_at' => now(),
        ]);

        if (! $isUp) {
            $checkWindow = config('platform.monitoring.uptime_interval_minutes', 5);
            $recentFailures = UptimeCheck::query()
                ->where('site_id', $site->id)
                ->where('is_up', false)
                ->where('checked_at', '>=', now()->subMinutes($checkWindow * 3 + 1))
                ->count();

            if ($recentFailures >= 3) {
                Notification::createAlert(
                    type: 'uptime_down',
                    title: "Site down: {$site->name}",
                    body: "{$site->domain} has failed {$recentFailures} consecutive uptime checks.",
                    siteId: $site->id,
                );
            }
        }
    }
}
