<?php

namespace Tests\Feature;

use App\Models\NewsletterCampaign;
use App\Models\NewsletterSubscriber;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ResendWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Allow unsigned requests in tests (no RESEND_WEBHOOK_SECRET configured).
        config()->set('services.resend.webhook_secret', null);
    }

    private function makeSiteWithCampaign(): array
    {
        $user = User::create([
            'name' => 'U', 'email' => 'rwc-'.uniqid().'@x.com',
            'password' => Hash::make('secret'), 'role' => 'admin',
        ]);
        $site = Site::create([
            'user_id' => $user->id, 'name' => 'S', 'slug' => 'rwc-'.uniqid(),
            'branch' => 'main', 'project_type' => 'static_html',
        ]);
        $campaign = NewsletterCampaign::create([
            'site_id' => $site->id, 'subject' => 'Test', 'status' => 'sent',
            'stats' => ['sent' => 10, 'opened' => 0, 'clicked' => 0, 'bounced' => 0],
        ]);
        $subscriber = NewsletterSubscriber::create([
            'site_id' => $site->id, 'email' => 'sub@example.com', 'status' => 'active',
        ]);

        return compact('site', 'campaign', 'subscriber');
    }

    public function test_bounce_marks_subscriber_as_bounced(): void
    {
        ['subscriber' => $sub, 'campaign' => $campaign] = $this->makeSiteWithCampaign();

        $this->postJson('/api/webhooks/resend', [
            'type' => 'email.bounced',
            'data' => ['to' => [$sub->email], 'tags' => ['campaign_id' => $campaign->id]],
        ])->assertOk();

        $this->assertDatabaseHas('newsletter_subscribers', [
            'email' => $sub->email, 'status' => 'bounced',
        ]);
    }

    public function test_bounce_increments_campaign_bounced_stat(): void
    {
        ['subscriber' => $sub, 'campaign' => $campaign] = $this->makeSiteWithCampaign();

        $this->postJson('/api/webhooks/resend', [
            'type' => 'email.bounced',
            'data' => ['to' => [$sub->email], 'tags' => ['campaign_id' => $campaign->id]],
        ])->assertOk();

        $this->assertSame(1, $campaign->fresh()->stats['bounced'] ?? 0);
    }

    public function test_complaint_unsubscribes_and_does_not_resubscribe_bounced(): void
    {
        ['subscriber' => $sub, 'campaign' => $campaign] = $this->makeSiteWithCampaign();
        $sub->update(['status' => 'bounced']);

        $this->postJson('/api/webhooks/resend', [
            'type' => 'email.complained',
            'data' => ['to' => [$sub->email], 'tags' => ['campaign_id' => $campaign->id]],
        ])->assertOk();

        $this->assertDatabaseHas('newsletter_subscribers', [
            'email' => $sub->email, 'status' => 'unsubscribed',
        ]);
    }

    public function test_open_increments_campaign_opened_stat(): void
    {
        ['campaign' => $campaign] = $this->makeSiteWithCampaign();

        $this->postJson('/api/webhooks/resend', [
            'type' => 'email.opened',
            'data' => ['tags' => ['campaign_id' => $campaign->id]],
        ])->assertOk();

        $this->assertSame(1, $campaign->fresh()->stats['opened'] ?? 0);
    }

    public function test_click_increments_campaign_clicked_stat(): void
    {
        ['campaign' => $campaign] = $this->makeSiteWithCampaign();

        $this->postJson('/api/webhooks/resend', [
            'type' => 'email.clicked',
            'data' => ['tags' => ['campaign_id' => $campaign->id]],
        ])->assertOk();

        $this->assertSame(1, $campaign->fresh()->stats['clicked'] ?? 0);
    }

    public function test_unknown_event_type_returns_ok_without_error(): void
    {
        $this->postJson('/api/webhooks/resend', [
            'type' => 'email.delivered',
            'data' => [],
        ])->assertOk();
    }

    public function test_missing_payload_returns_400(): void
    {
        $this->postJson('/api/webhooks/resend', [])->assertStatus(400);
    }

    public function test_signature_required_in_production(): void
    {
        config()->set('services.resend.webhook_secret', 'whsec_test');
        config()->set('app.env', 'production');

        $this->postJson('/api/webhooks/resend', [
            'type' => 'email.opened', 'data' => [],
        ])->assertStatus(401);
    }
}
