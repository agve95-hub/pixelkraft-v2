<?php

namespace App\Support;

use App\Models\SeoIssue;
use App\Models\Site;
use Illuminate\Support\Collection;

class SeoIssueSummary
{
    /**
     * Count of open SEO issues for the given site IDs (typically visible sites).
     *
     * @param  Collection<int, string>|array<string>  $siteIds
     */
    public static function openCountForSiteIds(Collection|array $siteIds): int
    {
        $ids = $siteIds instanceof Collection ? $siteIds->all() : $siteIds;

        if ($ids === []) {
            return 0;
        }

        return SeoIssue::query()
            ->open()
            ->whereIn('site_id', $ids)
            ->count();
    }

    public static function openCountForSite(Site $site): int
    {
        return SeoIssue::query()
            ->open()
            ->where('site_id', $site->id)
            ->count();
    }

    /**
     * Count of open issues with severity warning or error.
     */
    public static function openWarningCountForSite(Site $site): int
    {
        return SeoIssue::query()
            ->open()
            ->where('site_id', $site->id)
            ->where('severity', 'warning')
            ->count();
    }

    /**
     * Aggregated rows for the site SEO issues panel: grouped by stable key (code or message).
     *
     * @return Collection<int, array{severity: string, message: string, count: int<0, max>}>
     */
    public static function openAggregatesForSite(Site $site): Collection
    {
        $issues = SeoIssue::query()
            ->open()
            ->where('site_id', $site->id)
            ->get(['severity', 'message', 'code']);

        if ($issues->isEmpty()) {
            return collect();
        }

        $rank = ['error' => 3, 'warning' => 2, 'info' => 1];

        return $issues
            ->groupBy(fn (SeoIssue $i) => (string) ($i->code ?: $i->message))
            ->map(function (Collection $group) use ($rank) {
                $severity = (string) ($group->sortByDesc(fn (SeoIssue $i) => $rank[$i->severity] ?? 0)->first()->severity ?? '');
                $message = (string) ($group->first()->message ?? '');

                return [
                    'severity' => $severity,
                    'message' => $message,
                    'count' => $group->count(),
                ];
            })
            ->values();
    }
}
