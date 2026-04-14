<?php

namespace App\Livewire\Settings;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Http;
use Livewire\Component;

class DiscordWebhook extends Component
{
    public string $webhookUrl = '';

    public bool $hasExistingWebhook = false;

    public function mount(): void
    {
        // Never pre-fill the secret URL into the form — write-only.
        // Expose only whether one is already set so the view can show a status badge.
        $this->hasExistingWebhook = ! empty(auth()->user()->discord_webhook);
    }

    public function save(): void
    {
        $this->validate([
            // Must be a Discord webhook URL when provided.
            'webhookUrl' => ['nullable', 'url', 'max:500'],
        ]);

        $user = auth()->user();

        if ($this->webhookUrl !== '') {
            $user->update(['discord_webhook' => $this->webhookUrl]);
            $this->hasExistingWebhook = true;
        }

        // If the field is left blank we intentionally keep the existing value.
        $this->webhookUrl = '';

        session()->flash('success', 'Discord webhook updated.');
    }

    public function clearWebhook(): void
    {
        auth()->user()->update(['discord_webhook' => null]);
        $this->hasExistingWebhook = false;
        session()->flash('success', 'Discord webhook removed.');
    }

    public function testWebhook(): void
    {
        $url = auth()->user()->discord_webhook;

        if (empty($url)) {
            session()->flash('error', 'No Discord webhook is configured yet.');

            return;
        }

        try {
            Http::post($url, [
                'content' => 'pixelkraft test notification — webhook is working!',
            ]);
            session()->flash('success', 'Test message sent to Discord.');
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to send test: '.$e->getMessage());
        }
    }

    public function render(): View
    {
        return view('livewire.settings.discord-webhook');
    }
}
