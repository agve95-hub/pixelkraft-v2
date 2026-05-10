<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NewsletterCampaign;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Receives inbound webhook events from Resend for email delivery lifecycle.
 *
 * Configure in the Resend dashboard: Webhooks → Add endpoint
 *   URL: https://your-dashboard.example.com/api/webhooks/resend
 *   Events: email.bounced, email.complained, email.opened, email.clicked, email.delivered
 *
 * Optionally set RESEND_WEBHOOK_SECRET and enable signature verification below.
 */
class ResendWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $event = $request->input('type');
        $data = $request->input('data', []);

        if (empty($event) || ! is_array($data)) {
            return response()->json(['error' => 'Invalid payload'], 400);
        }

        Log::info("Resend webhook received: {$event}", ['data' => array_intersect_key($data, array_flip(['email_id', 'to', 'subject']))]);

        match ($event) {
            'email.bounced' => $this->handleBounce($data),
            'email.complained' => $this->handleComplaint($data),
            'email.opened' => $this->handleOpen($data),
            'email.clicked' => $this->handleClick($data),
            'email.delivered' => $this->handleDelivered($data),
            default => null,
        };

        return response()->json(['status' => 'ok']);
    }

    private function handleBounce(array $data): void
    {
        $email = $this->extractEmail($data);
        if (! $email) {
            return;
        }

        // Mark the subscriber as bounced so they are excluded from future sends.
        NewsletterSubscriber::query()
            ->where('email', $email)
            ->where('status', 'active')
            ->update(['status' => 'bounced']);

        // Increment the campaign bounce counter.
        $this->incrementCampaignStat($data, 'bounced');
    }

    private function handleComplaint(array $data): void
    {
        $email = $this->extractEmail($data);
        if (! $email) {
            return;
        }

        // Unsubscribe immediately on spam complaint — required by CAN-SPAM/GDPR.
        NewsletterSubscriber::query()
            ->where('email', $email)
            ->whereIn('status', ['active', 'bounced'])
            ->update(['status' => 'unsubscribed']);

        $this->incrementCampaignStat($data, 'bounced');
    }

    private function handleOpen(array $data): void
    {
        $this->incrementCampaignStat($data, 'opened');
    }

    private function handleClick(array $data): void
    {
        $this->incrementCampaignStat($data, 'clicked');
    }

    private function handleDelivered(array $data): void
    {
        // No-op for now — delivery is assumed when send succeeds in SendCampaigns.
    }

    private function incrementCampaignStat(array $data, string $stat): void
    {
        // Resend includes custom headers/tags in the webhook payload.
        // SendCampaigns must embed the campaign ID as a tag: ['campaign_id' => $id].
        $campaignId = $data['tags']['campaign_id'] ?? null;

        if (! $campaignId) {
            return;
        }

        $campaign = NewsletterCampaign::query()->find($campaignId);

        if (! $campaign) {
            return;
        }

        $stats = is_array($campaign->stats) ? $campaign->stats : [];
        $stats[$stat] = ($stats[$stat] ?? 0) + 1;

        $campaign->update(['stats' => $stats]);
    }

    private function extractEmail(array $data): ?string
    {
        $email = $data['to'][0] ?? $data['email'] ?? null;

        if (! $email || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return strtolower(trim($email));
    }
}
