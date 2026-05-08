<?php

namespace App\Services;

use App\Enums\DeployStatus;
use App\Jobs\DeploySiteJob;
use App\Models\Site;
use Illuminate\Support\Facades\DB;

class DeployDispatcher
{
    public function dispatch(Site $site, string $triggeredBy = 'manual'): bool
    {
        $queuedSite = DB::transaction(function () use ($site): ?Site {
            $fresh = Site::query()
                ->whereKey($site->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($fresh->deploy_status?->isActive()) {
                return null;
            }

            $fresh->update(['deploy_status' => DeployStatus::Queued]);

            return $fresh;
        });

        if (! $queuedSite) {
            return false;
        }

        DeploySiteJob::dispatch($queuedSite, $triggeredBy);

        return true;
    }
}
