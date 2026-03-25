<?php

namespace App\Notifications;

use App\Models\Site;
use App\Notifications\Channels\DiscordChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class SiteDown extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Site $site,
        public int $consecutiveFailures = 3,
    ) {}

    public function via(mixed $notifiable): array
    {
        return [DiscordChannel::class];
    }

    public function toDiscord(mixed $notifiable): array
    {
        return [
            'embeds' => [[
                'title'       => "🔴 Site down: {$this->site->name}",
                'description' => "{$this->site->domain} has failed {$this->consecutiveFailures} consecutive uptime checks.",
                'color'       => 0xEF4444, // red
                'fields'      => [
                    ['name' => 'Domain', 'value' => $this->site->domain ?? '—', 'inline' => true],
                    ['name' => 'Failures', 'value' => (string) $this->consecutiveFailures, 'inline' => true],
                ],
                'timestamp' => now()->toIso8601String(),
            ]],
        ];
    }
}
