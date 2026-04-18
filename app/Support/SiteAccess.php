<?php

namespace App\Support;

use App\Models\Site;
use Illuminate\Database\Eloquent\Builder;

class SiteAccess
{
    /**
     * @return Builder<Site>
     */
    public static function query(): Builder
    {
        return Site::query()->visibleTo(auth()->user());
    }

    public static function findOrFail(string $siteId): Site
    {
        return Site::findVisibleOrFail($siteId, auth()->user());
    }
}
