<?php

namespace App\Notifications;

use App\Models\DeployLog;
use App\Models\Site;
use App\Notifications\Channels\DiscordChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class DeployFailed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Site $site,
        public DeployLog $deployLog,
    ) {}

    public function via(mixed $notifiable): array
    {
        return [DiscordChannel::class];
    }

    public function toDiscord(mixed $notifiable): array
    {
        return [
            'embeds' => [[
                'title'       => "❌ Deploy failed: {$this->site->name}",
                'description' => $this->deployLog->commit_message ?? 'No commit message',
                'color'       => 0xEF4444, // red
                'fields'      => [
                    ['name' => 'Domain', 'value' => $this->site->domain ?? '—', 'inline' => true],
                    ['name' => 'Duration', 'value' => $this->deployLog->durationFormatted(), 'inline' => true],
                    ['name' => 'Trigger', 'value' => $this->deployLog->triggered_by, 'inline' => true],
                ],
                'timestamp' => now()->toIso8601String(),
            ]],
        ];
    }
}
