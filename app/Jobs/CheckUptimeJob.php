<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Models\Site;
use App\Models\UptimeCheck;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

/**
 * Checks uptime for a single site and records the result.
 *
 * Dispatched per-site from CheckUptime command instead of running sequentially
 * in the command process.  Using the monitoring queue (3 workers) gives true
 * parallel execution and avoids the N × 15s worst-case blocking.
 */
class CheckUptimeJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 20;

    public int $uniqueFor = 270; // 4.5 minutes — just under the 5-minute check interval

    public function __construct(public string $siteId)
    {
        $this->onQueue('monitoring');
    }

    public function uniqueId(): string
    {
        return $this->siteId;
    }

    public function handle(): void
    {
        $site = Site::query()->find($this->siteId);

        if (! $site || empty($site->domain)) {
            return;
        }

        $this->check($site);
    }

    private function check(Site $site): void
    {
        $startTime = microtime(true);
        $degradedAfterMs = (int) config('platform.monitoring.uptime_degraded_after_ms', 3000);
        $domain = (string) $site->domain;

        // SSRF guard.
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
