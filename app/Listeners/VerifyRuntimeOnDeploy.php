<?php

namespace App\Listeners;

use App\Events\SiteDeployed;
use App\Jobs\VerifyRuntimeHealthJob;
use App\Services\SiteRuntimeService;

final class VerifyRuntimeOnDeploy
{
    public function handle(SiteDeployed $event): void
    {
        $runtime = app(SiteRuntimeService::class);

        if (! $runtime->usesRuntimeServer($event->site)) {
            return;
        }

        VerifyRuntimeHealthJob::dispatch(
            $event->site->id,
            $event->log->id,
            $runtime->effectivePortFor($event->site),
        );
    }
}
