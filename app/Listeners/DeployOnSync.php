<?php

namespace App\Listeners;

use App\Events\SiteSynced;
use App\Services\DeployDispatcher;
use Illuminate\Support\Facades\Log;

final class DeployOnSync
{
    public function handle(SiteSynced $event): void
    {
        if (! $event->site->deploy_on_webhook || ! $event->hasChanges) {
            return;
        }

        if (app(DeployDispatcher::class)->dispatch($event->site, 'webhook')) {
            Log::info("DeployOnSync: dispatched deploy for [{$event->site->slug}]");
        } else {
            Log::info("DeployOnSync: deploy already active for [{$event->site->slug}]");
        }
    }
}
