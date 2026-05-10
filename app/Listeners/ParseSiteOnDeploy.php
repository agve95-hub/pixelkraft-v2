<?php

namespace App\Listeners;

use App\Events\SiteDeployed;
use App\Jobs\ParseSiteJob;

final class ParseSiteOnDeploy
{
    public function handle(SiteDeployed $event): void
    {
        ParseSiteJob::dispatch($event->site);
    }
}
