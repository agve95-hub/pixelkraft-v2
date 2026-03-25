<?php

namespace App\Notifications\Channels;

use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DiscordChannel
{
    public function send(mixed $notifiable, Notification $notification): void
    {
        $webhookUrl = $this->resolveWebhookUrl($notifiable);

        if (! $webhookUrl) {
            return;
        }

        if (! method_exists($notification, 'toDiscord')) {
            return;
        }

        $payload = $notification->toDiscord($notifiable);

        try {
            Http::post($webhookUrl, $payload);
        } catch (\Throwable $e) {
            Log::warning('Failed to send Discord notification', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function resolveWebhookUrl(mixed $notifiable): ?string
    {
        // Check user-level webhook first
        if ($notifiable instanceof User && $notifiable->discord_webhook) {
            return $notifiable->discord_webhook;
        }

        // Fall back to global webhook
        return config('pixelkraft.discord_webhook_url');
    }
}
