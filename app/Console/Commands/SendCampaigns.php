<?php

namespace App\Console\Commands;

use App\Models\NewsletterCampaign;
use App\Models\NewsletterSubscriber;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

class SendCampaigns extends Command
{
    protected $signature = 'platform:send-campaigns';

    protected $description = 'Send scheduled and queued newsletter campaigns via Resend';

    public function handle(): int
    {
        // Publish scheduled campaigns
        $scheduled = NewsletterCampaign::where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->get();

        foreach ($scheduled as $campaign) {
            $campaign->update(['status' => 'sending']);
        }

        // Process sending campaigns
        $sending = NewsletterCampaign::where('status', 'sending')
            ->with('site')
            ->get();

        foreach ($sending as $campaign) {
            $this->sendCampaign($campaign);
        }

        if ($sending->isNotEmpty()) {
            $this->info("Processed {$sending->count()} campaigns.");
        }

        return self::SUCCESS;
    }

    private function sendCampaign(NewsletterCampaign $campaign): void
    {
        if (empty($campaign->body_html)) {
            Log::warning("Campaign [{$campaign->id}] has no body HTML — marking failed.");
            $campaign->update([
                'status' => 'failed',
                'stats' => ['error' => 'Campaign body is empty.'],
            ]);

            return;
        }

        $apiKey = config('services.resend.key');

        if (! $apiKey) {
            Log::warning("Cannot send campaign [{$campaign->id}]: RESEND_API_KEY not configured");

            return;
        }

        $totalCount = NewsletterSubscriber::where('site_id', $campaign->site_id)
            ->where('status', 'active')
            ->count();

        if ($totalCount === 0) {
            $campaign->update([
                'status' => 'sent',
                'sent_at' => now(),
                'stats' => ['sent' => 0, 'opened' => 0, 'clicked' => 0, 'bounced' => 0],
            ]);

            return;
        }

        $fromEmail = config('mail.from.address', 'noreply@'.($campaign->site->domain ?? 'localhost'));
        $fromName = $campaign->site->name ?? config('mail.from.name', 'App');
        $sent = 0;
        $failed = 0;

        // Chunk to avoid loading the entire subscriber list into memory at once.
        NewsletterSubscriber::where('site_id', $campaign->site_id)
            ->where('status', 'active')
            ->orderBy('id')
            ->chunk(200, function ($subscribers) use ($apiKey, $fromName, $fromEmail, $campaign, &$sent, &$failed) {
                foreach ($subscribers as $subscriber) {
                    try {
                        $response = Http::withHeaders([
                            'Authorization' => "Bearer {$apiKey}",
                            'Content-Type' => 'application/json',
                        ])->post('https://api.resend.com/emails', [
                            'from' => "{$fromName} <{$fromEmail}>",
                            'to' => [$subscriber->email],
                            'subject' => $campaign->subject,
                            'html' => $this->personalizeHtml($campaign->body_html, $subscriber),
                            // Embed the campaign ID as a tag so the inbound Resend webhook
                            // (/api/webhooks/resend) can attribute bounces, opens, and
                            // clicks back to the correct campaign record.
                            'tags' => [
                                ['name' => 'campaign_id', 'value' => (string) $campaign->id],
                            ],
                        ]);

                        if ($response->successful()) {
                            $sent++;
                        } else {
                            $failed++;
                            Log::warning("Resend failed for [{$subscriber->email}]", ['status' => $response->status()]);

                            // Mark as bounced if permanent failure
                            if ($response->status() === 422) {
                                $subscriber->update(['status' => 'bounced']);
                            }
                        }
                    } catch (\Throwable $e) {
                        $failed++;
                        Log::warning("Email send error for [{$subscriber->email}]", ['error' => $e->getMessage()]);
                    }

                    // Rate limiting: ~10 emails per second
                    usleep(100000);
                }
            });

        $campaign->update([
            'status' => 'sent',
            'sent_at' => now(),
            'stats' => [
                'sent' => $sent,
                'failed' => $failed,
                'opened' => 0,
                'clicked' => 0,
                'bounced' => $failed,
            ],
        ]);

        Log::info("Campaign [{$campaign->subject}] sent: {$sent} delivered, {$failed} failed");
    }

    private function personalizeHtml(string $html, NewsletterSubscriber $subscriber): string
    {
        $replacements = [
            '{{email}}' => $subscriber->email,
            '{{name}}' => $subscriber->name ?? 'Subscriber',
            '{{unsubscribe_url}}' => URL::signedRoute('api.unsubscribe', ['subscriber' => $subscriber->id]),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $html);
    }
}
