<?php

namespace App\Services;

use App\Models\AnalyticsSnapshot;
use App\Models\Page;
use App\Models\Site;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AnalyticsAggregator
{
    /**
     * Sync analytics data for all active sites.
     */
    public function syncAll(): int
    {
        $sites = Site::where('is_active', true)->get();
        $synced = 0;

        foreach ($sites as $site) {
            try {
                $synced += $this->syncSite($site);
            } catch (\Throwable $e) {
                Log::warning("Analytics sync failed for [{$site->slug}]", ['error' => $e->getMessage()]);
            }
        }

        return $synced;
    }

    /**
     * Sync analytics for a single site.
     */
    public function syncSite(Site $site): int
    {
        $synced = 0;

        if ($site->cf_zone_id) {
            $synced += $this->syncCloudflare($site);
        }

        // GA sync would use Google Analytics Data API (requires service account credentials)
        // Implementation depends on google/analytics-data package
        if ($site->ga_property_id) {
            $synced += $this->syncGoogleAnalytics($site);
        }

        return $synced;
    }

    /**
     * Get aggregated stats for a site over a date range.
     */
    public function getSiteStats(Site $site, int $days = 30): array
    {
        $pageIds = $site->pages()->pluck('id');

        $snapshots = AnalyticsSnapshot::whereIn('page_id', $pageIds)
            ->where('date', '>=', now()->subDays($days))
            ->get();

        return [
            'total_visitors'  => $snapshots->sum('visitors'),
            'total_pageviews' => $snapshots->sum('pageviews'),
            'avg_bounce_rate' => round($snapshots->avg('bounce_rate') ?? 0, 1),
            'avg_session_sec' => (int) ($snapshots->avg('avg_session_sec') ?? 0),
            'daily' => $snapshots->groupBy('date')->map(fn ($group) => [
                'date'      => $group->first()->date->format('Y-m-d'),
                'visitors'  => $group->sum('visitors'),
                'pageviews' => $group->sum('pageviews'),
            ])->values()->toArray(),
            'top_pages' => $snapshots->groupBy('page_id')->map(fn ($group) => [
                'page_id'   => $group->first()->page_id,
                'visitors'  => $group->sum('visitors'),
                'pageviews' => $group->sum('pageviews'),
            ])->sortByDesc('pageviews')->take(10)->values()->toArray(),
        ];
    }

    /**
     * Get stats for a single page.
     */
    public function getPageStats(Page $page, int $days = 30): array
    {
        $snapshots = $page->analyticsSnapshots()
            ->where('date', '>=', now()->subDays($days))
            ->orderBy('date')
            ->get();

        return [
            'total_visitors'  => $snapshots->sum('visitors'),
            'total_pageviews' => $snapshots->sum('pageviews'),
            'avg_bounce_rate' => round($snapshots->avg('bounce_rate') ?? 0, 1),
            'daily' => $snapshots->groupBy('date')->map(fn ($group) => [
                'date'      => $group->first()->date->format('Y-m-d'),
                'visitors'  => $group->sum('visitors'),
                'pageviews' => $group->sum('pageviews'),
            ])->values()->toArray(),
        ];
    }

    // ── Cloudflare Analytics ────────────────────

    private function syncCloudflare(Site $site): int
    {
        $token = config('pixelkraft.cloudflare_api_token', env('CLOUDFLARE_API_TOKEN'));

        if (! $token || ! $site->cf_zone_id) {
            return 0;
        }

        $yesterday = now()->subDay()->format('Y-m-d');

        try {
            $response = Http::withToken($token)
                ->post('https://api.cloudflare.com/client/v4/graphql', [
                    'query' => $this->cloudflareQuery(),
                    'variables' => [
                        'zoneTag' => $site->cf_zone_id,
                        'date'    => $yesterday,
                    ],
                ]);

            if (! $response->successful()) {
                Log::warning("Cloudflare API error for [{$site->slug}]", ['status' => $response->status()]);
                return 0;
            }

            $data = $response->json('data.viewer.zones.0.httpRequests1dGroups.0') ?? null;

            if (! $data) {
                return 0;
            }

            $visitors = $data['uniq']['uniques'] ?? 0;
            $pageviews = $data['sum']['requests'] ?? 0;

            // Store as site-level snapshot (assign to homepage)
            $homepage = $site->pages()->where('url_path', '/')->first();

            if ($homepage) {
                AnalyticsSnapshot::updateOrCreate(
                    [
                        'page_id' => $homepage->id,
                        'date'    => $yesterday,
                        'source'  => 'cloudflare',
                    ],
                    [
                        'visitors'  => $visitors,
                        'pageviews' => $pageviews,
                        'created_at' => now(),
                    ]
                );

                return 1;
            }

        } catch (\Throwable $e) {
            Log::warning("Cloudflare sync error for [{$site->slug}]", ['error' => $e->getMessage()]);
        }

        return 0;
    }

    private function cloudflareQuery(): string
    {
        return <<<'GRAPHQL'
        query ($zoneTag: String!, $date: String!) {
            viewer {
                zones(filter: {zoneTag: $zoneTag}) {
                    httpRequests1dGroups(filter: {date: $date}, limit: 1) {
                        sum {
                            requests
                            bytes
                        }
                        uniq {
                            uniques
                        }
                    }
                }
            }
        }
        GRAPHQL;
    }

    // ── Google Analytics ─────────────────────────

    private function syncGoogleAnalytics(Site $site): int
    {
        // GA4 Data API integration
        // Requires: composer require google/analytics-data
        // Uses service account credentials from GOOGLE_ANALYTICS_CREDENTIALS_PATH
        //
        // This is a placeholder — full implementation requires:
        // 1. Google Cloud service account with Analytics Data API access
        // 2. Property linked to the service account
        // 3. google/analytics-data PHP package
        //
        // The structure would fetch per-page metrics via the RunReport API
        // and store them as AnalyticsSnapshot records.

        Log::info("GA sync placeholder for [{$site->slug}] — configure service account to enable");

        return 0;
    }
}
