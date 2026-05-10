<?php

namespace App\Listeners;

use App\Events\SiteSynced;
use App\Jobs\ParseSiteJob;

final class ParseSiteOnSync
{
    public function handle(SiteSynced $event): void
    {
        if ($event->hasChanges) {
            ParseSiteJob::dispatch($event->site);
        }
    }
}
