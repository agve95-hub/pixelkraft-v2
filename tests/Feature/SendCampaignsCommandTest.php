<?php

namespace Tests\Feature;

use App\Models\NewsletterCampaign;
use App\Models\NewsletterSubscriber;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SendCampaignsCommandTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::create([
            'name' => 'U',
            'email' => 'sc-'.uniqid().'@x.com',
            'password' => Hash::make('secret'),
            'role' => 'admin',
        ]);
    }

    private function makeSite(User $user): Site
    {
        return Site::create([
            'user_id' => $user->id,
            'name' => 'Campaign Site',
            'slug' => 'sc-'.uniqid(),
            'repo_url' => 'https://github.com/example/sc',
            'branch' => 'main',
            'project_type' => 'static_html',
        ]);
    }

    private function makeCampaign(Site $site, array $attrs = []): NewsletterCampaign
    {
        return NewsletterCampaign::create(array_merge([
            'site_id' => $site->id,
            'subject' => 'Test Campaign',
            'body_html' => '<p>Hello {{name}}</p>',
            'status' => 'scheduled',
            'scheduled_at' => now()->subMinute(),
        ], $attrs));
    }

    private function makeSubscriber(Site $site, array $attrs = []): NewsletterSubscriber
    {
        return NewsletterSubscriber::create(array_merge([
            'site_id' => $site->id,
            'email' => uniqid().'@subscriber.com',
            'name' => 'Alice',
            'status' => 'active',
        ], $attrs));
    }

    // ── scheduling ───────────────────────────────

    public function test_promotes_scheduled_past_due_campaigns_to_sending(): void
    {
        Http::fake(['*' => Http::response(['id' => 'r1'], 200)]);
        Config::set('services.resend.key', 'fake-key');

        $user = $this->makeUser();
        $site = $this->makeSite($user);
        $campaign = $this->makeCampaign($site, ['status' => 'scheduled', 'scheduled_at' => now()->subHour()]);
        $this->makeSubscriber($site);

        $this->artisan('platform:send-campaigns')->assertSuccessful();

        $campaign->refresh();
        $this->assertSame('sent', $campaign->status);
    }

    public function test_does_not_promote_future_scheduled_campaigns(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);
        $campaign = $this->makeCampaign($site, ['status' => 'scheduled', 'scheduled_at' => now()->addHour()]);

        $this->artisan('platform:send-campaigns')->assertSuccessful();

        $campaign->refresh();
        $this->assertSame('scheduled', $campaign->status);
    }

    // ── sending ──────────────────────────────────

    public function test_sends_email_to_active_subscribers_via_resend(): void
    {
        Http::fake(['https://api.resend.com/*' => Http::response(['id' => 'email-1'], 200)]);
        Config::set('services.resend.key', 'test-key');

        $user = $this->makeUser();
        $site = $this->makeSite($user);
        $campaign = $this->makeCampaign($site, ['status' => 'sending']);
        $this->makeSubscriber($site);

        $this->artisan('platform:send-campaigns')->assertSuccessful();

        Http::assertSent(fn ($req) => $req->url() === 'https://api.resend.com/emails');

        $campaign->refresh();
        $this->assertSame('sent', $campaign->status);
        $this->assertSame(1, $campaign->stats['sent']);
    }

    public function test_marks_campaign_sent_with_zero_when_no_subscribers(): void
    {
        Config::set('services.resend.key', 'test-key');

        $user = $this->makeUser();
        $site = $this->makeSite($user);
        $campaign = $this->makeCampaign($site, ['status' => 'sending']);

        $this->artisan('platform:send-campaigns')->assertSuccessful();

        $campaign->refresh();
        $this->assertSame('sent', $campaign->status);
        $this->assertSame(0, $campaign->stats['sent']);
    }

    public function test_skips_sending_when_no_resend_key_configured(): void
    {
        Http::fake();
        Config::set('services.resend.key', null);

        $user = $this->makeUser();
        $site = $this->makeSite($user);
        $this->makeCampaign($site, ['status' => 'sending']);
        $this->makeSubscriber($site);

        $this->artisan('platform:send-campaigns')->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_personalizes_html_with_subscriber_name_and_email(): void
    {
        $captured = [];
        Http::fake([
            'https://api.resend.com/*' => function ($request) use (&$captured) {
                $captured[] = $request->data();

                return Http::response(['id' => 'r'], 200);
            },
        ]);
        Config::set('services.resend.key', 'test-key');

        $user = $this->makeUser();
        $site = $this->makeSite($user);
        $this->makeCampaign($site, ['status' => 'sending', 'body_html' => 'Hello {{name}}, your email is {{email}}']);
        $this->makeSubscriber($site, ['name' => 'Bob', 'email' => 'bob@example.com']);

        $this->artisan('platform:send-campaigns')->assertSuccessful();

        $this->assertNotEmpty($captured);
        $this->assertStringContainsString('Hello Bob', $captured[0]['html']);
        $this->assertStringContainsString('bob@example.com', $captured[0]['html']);
    }

    public function test_marks_subscriber_bounced_on_422_response(): void
    {
        Http::fake(['https://api.resend.com/*' => Http::response(['error' => 'Invalid email'], 422)]);
        Config::set('services.resend.key', 'test-key');

        $user = $this->makeUser();
        $site = $this->makeSite($user);
        $this->makeCampaign($site, ['status' => 'sending']);
        $subscriber = $this->makeSubscriber($site);

        $this->artisan('platform:send-campaigns')->assertSuccessful();

        $subscriber->refresh();
        $this->assertSame('bounced', $subscriber->status);
    }

    public function test_does_not_send_to_inactive_subscribers(): void
    {
        Http::fake();
        Config::set('services.resend.key', 'test-key');

        $user = $this->makeUser();
        $site = $this->makeSite($user);
        $this->makeCampaign($site, ['status' => 'sending']);
        $this->makeSubscriber($site, ['status' => 'unsubscribed']);

        $this->artisan('platform:send-campaigns')->assertSuccessful();

        Http::assertNothingSent();
    }
}
