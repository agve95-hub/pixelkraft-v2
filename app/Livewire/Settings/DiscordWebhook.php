<?php

namespace App\Livewire\Settings;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class DiscordWebhook extends Component
{
    public string $webhookUrl = '';

    public function mount(): void
    {
        $this->webhookUrl = auth()->user()->discord_webhook ?? '';
    }

    public function save(): void
    {
        $this->validate([
            'webhookUrl' => 'nullable|url|max:500',
        ]);

        auth()->user()->update([
            'discord_webhook' => $this->webhookUrl ?: null,
        ]);

        session()->flash('success', 'Discord webhook updated.');
    }

    public function testWebhook(): void
    {
        if (empty($this->webhookUrl)) {
            return;
        }

        try {
            \Illuminate\Support\Facades\Http::post($this->webhookUrl, [
                'content' => '✅ pixelkraft test notification — webhook is working!',
            ]);
            session()->flash('success', 'Test message sent to Discord.');
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to send test: ' . $e->getMessage());
        }
    }

    public function render(): View
    {
        return view('livewire.settings.discord-webhook');
    }
}
