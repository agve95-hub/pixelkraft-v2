<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Delivers platform dashboard notifications to a user's Discord webhook URL.
 *
 * Called from Notification::createAlert() after the DB record is created.
 * The webhook URL is stored per-user (encrypted) as User.discord_webhook.
 */
class DiscordNotifier
{
    /**
     * Send a platform notification to every admin's Discord webhook that has one
     * configured.  Site-scoped notifications are routed to the site owner's
     * webhook (if set); global notifications go to all admins' webhooks.
     */
    public function send(Notification $notification): void
    {
        $recipients = $this->resolveRecipients($notification);

        foreach ($recipients as $user) {
            $webhookUrl = $user->discord_webhook;

            if (empty($webhookUrl)) {
                continue;
            }

            $this->post($webhookUrl, $notification);
        }
    }

    /**
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function resolveRecipients(Notification $notification): \Illuminate\Support\Collection
    {
        if ($notification->site_id) {
            // Site-scoped: notify the site owner + all admins.
            return User::query()
                ->where(function ($q) use ($notification) {
                    $q->where('role', 'admin')
                        ->orWhereHas('sites', fn ($s) => $s->where('id', $notification->site_id));
                })
                ->whereNotNull('discord_webhook')
                ->get();
        }

        // Global notification: all admins.
        return User::query()
            ->where('role', 'admin')
            ->whereNotNull('discord_webhook')
            ->get();
    }

    private function post(string $webhookUrl, Notification $notification): void
    {
        $colour = match ($notification->type) {
            'deploy_failed', 'uptime_down', 'broken_links', 'ssl_expiring' => 0xEF4444, // red
            'form_received' => 0x22C55E, // green
            default => 0x6366F1, // indigo
        };

        $payload = [
            'embeds' => [[
                'title' => $notification->title,
                'description' => $notification->body ?? '',
                'color' => $colour,
                'footer' => ['text' => 'platform · '.now()->toDateTimeString()],
            ]],
        ];

        try {
            Http::timeout(5)
                ->withOptions(['http_errors' => false])
                ->post($webhookUrl, $payload);
        } catch (\Throwable $e) {
            Log::warning('DiscordNotifier: failed to post notification', [
                'notification_id' => $notification->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
