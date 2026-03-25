<?php

namespace App\Notifications;

use App\Models\FormSubmission;
use App\Models\Site;
use App\Notifications\Channels\DiscordChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class FormReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Site $site,
        public FormSubmission $submission,
    ) {}

    public function via(mixed $notifiable): array
    {
        return [DiscordChannel::class];
    }

    public function toDiscord(mixed $notifiable): array
    {
        $data = $this->submission->data;
        $from = $data['email'] ?? $data['name'] ?? 'Anonymous';

        $fields = [
            ['name' => 'Site', 'value' => $this->site->name, 'inline' => true],
            ['name' => 'Form', 'value' => $this->submission->form_name, 'inline' => true],
            ['name' => 'From', 'value' => $from, 'inline' => true],
        ];

        // Include message preview if present
        $message = $data['message'] ?? $data['body'] ?? null;
        if ($message) {
            $fields[] = ['name' => 'Message', 'value' => str()->limit($message, 200)];
        }

        return [
            'embeds' => [[
                'title'       => "📬 New form submission: {$this->site->name}",
                'color'       => 0x3B82F6, // blue
                'fields'      => $fields,
                'timestamp'   => now()->toIso8601String(),
            ]],
        ];
    }
}
