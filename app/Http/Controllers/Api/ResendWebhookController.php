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
 * Copy the signing secret from the Resend dashboard and set in .env:
 *   RESEND_WEBHOOK_SECRET=whsec_...
 *
 * Without the secret, any IP can POST fake bounce/complaint events and silently
 * unsubscribe real subscribers.  The endpoint rejects all requests when no
 * secret is configured in production.
 */
class ResendWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        if (! $this->verifySignature($request)) {
            return response()->json(['error' => 'Invalid signature'], 401);
        }

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

    /**
     * Verify the Svix webhook signature that Resend attaches to every delivery.
     *
     * Resend uses Svix under the hood. The three required headers are:
     *   svix-id        — unique message ID
     *   svix-timestamp — Unix timestamp (seconds)
     *   svix-signature — base64-encoded HMAC-SHA256 of "{id}.{timestamp}.{body}"
     *
     * Rejects when RESEND_WEBHOOK_SECRET is not set in production, so operators
     * cannot forget to configure it and accidentally leave the endpoint open.
     */
    private function verifySignature(Request $request): bool
    {
        $secret = config('services.resend.webhook_secret');

        // In local/test environments allow unsigned requests so developers can
        // POST test payloads without generating valid signatures.
        if (! $secret) {
            return app()->isLocal() || app()->runningUnitTests();
        }

        $msgId = $request->header('svix-id');
        $msgTs = $request->header('svix-timestamp');
        $msgSig = $request->header('svix-signature');

        if (! $msgId || ! $msgTs || ! $msgSig) {
            return false;
        }

        // Reject timestamps more than 5 minutes old (replay attack protection).
        if (abs(time() - (int) $msgTs) > 300) {
            return false;
        }

        $toSign = "{$msgId}.{$msgTs}.".$request->getContent();

        // The secret is prefixed with "whsec_"; strip it before base64-decoding.
        $rawSecret = base64_decode(str_replace('whsec_', '', $secret));
        $expected = base64_encode(hash_hmac('sha256', $toSign, $rawSecret, true));

        // The svix-signature header may contain multiple signatures (e.g. during
        // secret rotation).  Accept if any of them matches.
        foreach (explode(' ', $msgSig) as $sig) {
            $parts = explode(',', $sig, 2);
            if (count($parts) === 2 && hash_equals($expected, $parts[1])) {
                return true;
            }
        }

        return false;
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
