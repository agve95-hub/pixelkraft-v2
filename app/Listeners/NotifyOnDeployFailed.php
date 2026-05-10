<?php

namespace App\Listeners;

use App\Events\DeployFailed;
use App\Models\Notification;
use Illuminate\Support\Facades\Log;

final class NotifyOnDeployFailed
{
    public function handle(DeployFailed $event): void
    {
        try {
            Notification::createAlert(
                type: 'deploy_failed',
                title: "Deploy failed: {$event->site->name}",
                body: mb_substr($event->error, 0, 500),
                siteId: $event->site->id,
            );
        } catch (\Throwable $e) {
            Log::error('NotifyOnDeployFailed: could not create notification', [
                'site' => $event->site->slug,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
