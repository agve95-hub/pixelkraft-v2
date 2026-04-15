<?php

namespace App\Services;

use App\Models\AnalyticsEvent;
use App\Models\AnalyticsSnapshot;
use App\Models\Page;
use App\Models\Site;
use Google\Analytics\Data\V1beta\Client\BetaAnalyticsDataClient;
use Google\Analytics\Data\V1beta\DateRange;
use Google\Analytics\Data\V1beta\Dimension;
use Google\Analytics\Data\V1beta\Filter;
use Google\Analytics\Data\V1beta\Filter\StringFilter;
use Google\Analytics\Data\V1beta\Filter\StringFilter\MatchType;
use Google\Analytics\Data\V1beta\FilterExpression;
use Google\Analytics\Data\V1beta\Metric;
use Google\Analytics\Data\V1beta\RunReportRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
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

        $synced += $this->syncFirstPartyTracker($site);

        if ($site->cf_zone_id) {
            $synced += $this->syncCloudflare($site);
        }

        if ($site->ga_property_id) {
            $synced += $this->syncGoogleAnalytics($site);
        }

        return $synced;
    }

    public function summarizeSiteEvents(Site $site, int $days = 30): array
    {
        $since = now()->subDays($days);

        // Aggregate counts directly in the database instead of hydrating all rows.
        $totals = AnalyticsEvent::query()
            ->where('site_id', $site->id)
            ->where('occurred_at', '>=', $since)
            ->selectRaw('
                COUNT(*) as total_events,
                SUM(CASE WHEN event_name = ? THEN 1 ELSE 0 END) as page_views,
                SUM(CASE WHEN event_name = ? THEN 1 ELSE 0 END) as forms,
                SUM(CASE WHEN event_name NOT IN (?, ?) THEN 1 ELSE 0 END) as interactions
            ', ['page_view', 'form_submit', 'page_view', 'form_submit'])
            ->first();

        $topEvents = AnalyticsEvent::query()
            ->where('site_id', $site->id)
            ->where('occurred_at', '>=', $since)
            ->selectRaw('event_name, COUNT(*) as count')
            ->groupBy('event_name')
            ->orderByDesc('count')
            ->limit(8)
            ->get()
            ->map(fn ($row) => [
                'event_name' => $row->event_name,
                'count' => (int) $row->count,
            ])
            ->values()
            ->all();

        return [
            'total_events' => (int) ($totals->total_events ?? 0),
            'page_views' => (int) ($totals->page_views ?? 0),
            'forms' => (int) ($totals->forms ?? 0),
            'interactions' => (int) ($totals->interactions ?? 0),
            'top_events' => $topEvents,
        ];
    }

    /**
     * Aggregated traffic for a site. Prefers GA4 organic (SEO) snapshots; falls back to any source.
     *
     * @return array{
     *   total_visitors: int,
     *   total_pageviews: int,
     *   avg_bounce_rate: float,
     *   avg_session_sec: int,
     *   daily: list<array{date: string, visitors: int, pageviews: int}>,
     *   top_pages: list<array{page_id: string, visitors: int, pageviews: int}>,
     *   traffic_label: string
     * }
     */
    public function getSiteStats(Site $site, int $days = 30): array
    {
        $pageIds = $site->pages()->pluck('id');

        $organic = $this->aggregateSnapshots($pageIds, $days, AnalyticsSnapshot::SOURCE_GOOGLE_ORGANIC);
        $hasOrganic = $organic['total_visitors'] > 0 || count($organic['daily']) > 0;

        if ($hasOrganic) {
            $organic['traffic_label'] = 'Organic search (Google)';

            return $organic;
        }

        $fallback = $this->aggregateSnapshots($pageIds, $days, null);
        $fallback['traffic_label'] = 'All sources';

        return $fallback;
    }

    /**
     * @return array{
     *   total_visitors: int,
     *   total_pageviews: int,
     *   avg_bounce_rate: float,
     *   avg_session_sec: int,
     *   daily: list<array{date: string, visitors: int, pageviews: int}>,
     *   top_pages: list<array{page_id: string, visitors: int, pageviews: int}>
     * }
     */
    public function getPageStats(Page $page, int $days = 30): array
    {
        $organic = $this->aggregatePageSnapshots($page, $days, AnalyticsSnapshot::SOURCE_GOOGLE_ORGANIC);
        if ($organic['total_visitors'] > 0 || count($organic['daily']) > 0) {
            return $organic;
        }

        return $this->aggregatePageSnapshots($page, $days, null);
    }

    /**
     * @param  Collection<int, string>  $pageIds
     */
    private function aggregateSnapshots(Collection $pageIds, int $days, ?string $source): array
    {
        if ($pageIds->isEmpty()) {
            return [
                'total_visitors' => 0,
                'total_pageviews' => 0,
                'avg_bounce_rate' => 0.0,
                'avg_session_sec' => 0,
                'daily' => [],
                'top_pages' => [],
            ];
        }

        $query = AnalyticsSnapshot::whereIn('page_id', $pageIds)
            ->where('date', '>=', now()->subDays($days));

        if ($source !== null) {
            $query->where('source', $source);
        }

        $snapshots = $query->get();

        return [
            'total_visitors' => (int) $snapshots->sum('visitors'),
            'total_pageviews' => (int) $snapshots->sum('pageviews'),
            'avg_bounce_rate' => round((float) ($snapshots->avg('bounce_rate') ?? 0), 1),
            'avg_session_sec' => (int) ($snapshots->avg('avg_session_sec') ?? 0),
            'daily' => $snapshots->groupBy(fn ($s) => $s->date->format('Y-m-d'))
                ->map(fn ($group) => [
                    'date' => $group->first()->date->format('Y-m-d'),
                    'visitors' => (int) $group->sum('visitors'),
                    'pageviews' => (int) $group->sum('pageviews'),
                ])
                ->sortBy('date')
                ->values()
                ->all(),
            'top_pages' => $snapshots->groupBy('page_id')
                ->map(fn ($group) => [
                    'page_id' => (string) $group->first()->page_id,
                    'visitors' => (int) $group->sum('visitors'),
                    'pageviews' => (int) $group->sum('pageviews'),
                ])
                ->sortByDesc('pageviews')
                ->take(10)
                ->values()
                ->all(),
        ];
    }

    private function aggregatePageSnapshots(Page $page, int $days, ?string $source): array
    {
        $query = $page->analyticsSnapshots()
            ->where('date', '>=', now()->subDays($days))
            ->orderBy('date');

        if ($source !== null) {
            $query->where('source', $source);
        }

        $snapshots = $query->get();

        return [
            'total_visitors' => (int) $snapshots->sum('visitors'),
            'total_pageviews' => (int) $snapshots->sum('pageviews'),
            'avg_bounce_rate' => round((float) ($snapshots->avg('bounce_rate') ?? 0), 1),
            'daily' => $snapshots->groupBy(fn ($s) => $s->date->format('Y-m-d'))
                ->map(fn ($group) => [
                    'date' => $group->first()->date->format('Y-m-d'),
                    'visitors' => (int) $group->sum('visitors'),
                    'pageviews' => (int) $group->sum('pageviews'),
                ])
                ->sortBy('date')
                ->values()
                ->all(),
        ];
    }

    private function syncFirstPartyTracker(Site $site): int
    {
        $pagesByPath = $site->pages()
            ->get()
            ->keyBy(fn (Page $page) => $this->normalizePath($page->url_path ?: '/'));

        $events = AnalyticsEvent::query()
            ->where('site_id', $site->id)
            ->where('occurred_at', '>=', now()->subDays(30)->startOfDay())
            ->get();

        if ($events->isEmpty()) {
            $site->update([
                'visitors_today' => 0,
                'visitors_change_percent' => null,
            ]);

            return 0;
        }

        $groups = $events->groupBy(function (AnalyticsEvent $event): string {
            return $event->occurred_at->toDateString().'|'.$this->normalizePath($event->path ?: '/');
        });

        $writes = 0;

        foreach ($groups as $key => $group) {
            [$date, $path] = explode('|', $key, 2);
            $page = $pagesByPath->get($path) ?? $pagesByPath->get('/');

            if (! $page) {
                continue;
            }

            $visitorIds = $group
                ->pluck('visitor_id')
                ->filter()
                ->unique()
                ->count();

            $pageViews = $group->where('event_name', 'page_view')->count();

            $customEvents = $group
                ->reject(fn (AnalyticsEvent $event) => $event->event_name === 'page_view')
                ->groupBy('event_name')
                ->map(fn (Collection $events, string $eventName) => $events->count())
                ->all();

            AnalyticsSnapshot::updateOrCreate(
                [
                    'page_id' => $page->id,
                    'date' => $date,
                    'source' => AnalyticsSnapshot::SOURCE_PIXELKRAFT_TRACKER,
                ],
                [
                    'visitors' => max($visitorIds, $pageViews > 0 ? 1 : 0),
                    'pageviews' => $pageViews,
                    'custom_events' => $customEvents,
                    'created_at' => now(),
                ]
            );

            $writes++;
        }

        $this->updateDashboardProjectionFields($site);

        return $writes;
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
                        'date' => $yesterday,
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

            $homepage = $site->pages()->where('url_path', '/')->first();

            if ($homepage) {
                AnalyticsSnapshot::updateOrCreate(
                    [
                        'page_id' => $homepage->id,
                        'date' => $yesterday,
                        'source' => 'cloudflare',
                    ],
                    [
                        'visitors' => $visitors,
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

    // ── Google Analytics (GA4) — organic / SEO ─────────────────────────

    private function syncGoogleAnalytics(Site $site): int
    {
        $credentialsPath = config('pixelkraft.google_analytics_credentials_path');
        $propertyId = $this->normalizeGaPropertyId($site->ga_property_id);

        if (! $credentialsPath || ! File::isReadable($credentialsPath) || ! $propertyId) {
            Log::info("GA4 sync skipped for [{$site->slug}] — missing credentials or property ID");

            return 0;
        }

        $pagesByPath = $site->pages()->get()->keyBy(fn (Page $p) => $this->normalizePath($p->url_path));

        if ($pagesByPath->isEmpty()) {
            return 0;
        }

        try {
            $client = new BetaAnalyticsDataClient([
                'credentials' => $credentialsPath,
            ]);
        } catch (\Throwable $e) {
            Log::warning("GA4 client init failed for [{$site->slug}]", ['error' => $e->getMessage()]);

            return 0;
        }

        $startDate = now()->subDays(30)->format('Y-m-d');
        $endDate = now()->subDay()->format('Y-m-d');

        $organicFilter = new FilterExpression([
            'filter' => new Filter([
                'field_name' => 'sessionDefaultChannelGroup',
                'string_filter' => new StringFilter([
                    'match_type' => MatchType::EXACT,
                    'value' => 'Organic Search',
                ]),
            ]),
        ]);

        $updated = 0;
        $offset = 0;
        $limit = 100_000;

        try {
            do {
                $request = new RunReportRequest([
                    'property' => 'properties/'.$propertyId,
                    'date_ranges' => [
                        new DateRange([
                            'start_date' => $startDate,
                            'end_date' => $endDate,
                        ]),
                    ],
                    'dimensions' => [
                        new Dimension(['name' => 'date']),
                        new Dimension(['name' => 'pagePath']),
                    ],
                    'metrics' => [
                        new Metric(['name' => 'activeUsers']),
                        new Metric(['name' => 'screenPageViews']),
                    ],
                    'dimension_filter' => $organicFilter,
                    'limit' => $limit,
                    'offset' => $offset,
                ]);

                $response = $client->runReport($request);

                $rowCount = count($response->getRows());
                foreach ($response->getRows() as $row) {
                    $dims = $row->getDimensionValues();
                    $metrics = $row->getMetricValues();
                    $dateRaw = $dims[0]->getValue();
                    $pathRaw = $dims[1]->getValue() ?? '';

                    $date = $this->gaDateToSql($dateRaw);
                    $path = $this->normalizePath(rawurldecode($pathRaw));
                    $page = $pagesByPath->get($path);

                    if (! $page || ! $date) {
                        continue;
                    }

                    $visitors = (int) ($metrics[0]->getValue() ?? 0);
                    $pageviews = (int) ($metrics[1]->getValue() ?? 0);

                    AnalyticsSnapshot::updateOrCreate(
                        [
                            'page_id' => $page->id,
                            'date' => $date,
                            'source' => AnalyticsSnapshot::SOURCE_GOOGLE_ORGANIC,
                        ],
                        [
                            'visitors' => $visitors,
                            'pageviews' => $pageviews,
                            'created_at' => now(),
                        ]
                    );
                    $updated++;
                }

                $offset += $rowCount;
            } while ($rowCount === $limit);
        } catch (\Throwable $e) {
            Log::warning("GA4 Data API error for [{$site->slug}]", ['error' => $e->getMessage()]);

            return 0;
        }

        return $updated;
    }

    private function normalizeGaPropertyId(?string $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        $raw = trim($raw);
        if (str_starts_with($raw, 'properties/')) {
            $raw = substr($raw, strlen('properties/'));
        }

        return preg_match('/^\d+$/', $raw) ? $raw : null;
    }

    private function gaDateToSql(string $yyyymmdd): ?string
    {
        if (strlen($yyyymmdd) !== 8 || ! ctype_digit($yyyymmdd)) {
            return null;
        }

        return substr($yyyymmdd, 0, 4).'-'.substr($yyyymmdd, 4, 2).'-'.substr($yyyymmdd, 6, 2);
    }

    private function normalizePath(string $path): string
    {
        $path = '/'.ltrim($path, '/');
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        return $path === '' ? '/' : $path;
    }

    private function updateDashboardProjectionFields(Site $site): void
    {
        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();

        $todayVisitors = AnalyticsSnapshot::query()
            ->join('pages', 'pages.id', '=', 'analytics_snapshots.page_id')
            ->where('pages.site_id', $site->id)
            ->where('analytics_snapshots.source', AnalyticsSnapshot::SOURCE_PIXELKRAFT_TRACKER)
            ->whereDate('analytics_snapshots.date', $today)
            ->sum('analytics_snapshots.visitors');

        $yesterdayVisitors = AnalyticsSnapshot::query()
            ->join('pages', 'pages.id', '=', 'analytics_snapshots.page_id')
            ->where('pages.site_id', $site->id)
            ->where('analytics_snapshots.source', AnalyticsSnapshot::SOURCE_PIXELKRAFT_TRACKER)
            ->whereDate('analytics_snapshots.date', $yesterday)
            ->sum('analytics_snapshots.visitors');

        $changePercent = null;
        if ((int) $yesterdayVisitors > 0) {
            $changePercent = round((((int) $todayVisitors - (int) $yesterdayVisitors) / (int) $yesterdayVisitors) * 100, 2);
        }

        $site->update([
            'visitors_today' => (int) $todayVisitors,
            'visitors_change_percent' => $changePercent,
        ]);
    }
}
