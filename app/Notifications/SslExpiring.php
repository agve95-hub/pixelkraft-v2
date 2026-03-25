<?php

namespace App\Notifications;

use App\Models\Site;
use App\Notifications\Channels\DiscordChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class SslExpiring extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Site $site,
    ) {}

    public function via(mixed $notifiable): array
    {
        return [DiscordChannel::class];
    }

    public function toDiscord(mixed $notifiable): array
    {
        $daysLeft = now()->diffInDays($this->site->ssl_expires_at);

        return [
            'embeds' => [[
                'title'       => "⚠️ SSL expiring: {$this->site->domain}",
                'description' => "Certificate expires in {$daysLeft} days.",
                'color'       => 0xF59E0B, // amber
                'fields'      => [
                    ['name' => 'Expires', 'value' => $this->site->ssl_expires_at->format('M j, Y'), 'inline' => true],
                    ['name' => 'Site', 'value' => $this->site->name, 'inline' => true],
                ],
                'timestamp' => now()->toIso8601String(),
            ]],
        ];
    }
}
