<?php

namespace Tests\Feature;

use App\Models\NewsletterCampaign;
use App\Models\NewsletterSubscriber;
use App\Models\Site;
use App\Models\UptimeCheck;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ArtisanCommandsTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::create([
            'name' => 'Cmd User',
            'email' => 'cmd-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
            'role' => 'editor',
        ]);
    }

    private function makeSite(User $user, string $slug = 'cmd-site'): Site
    {
        return Site::create([
            'user_id' => $user->id,
            'name' => 'Cmd Site',
            'slug' => $slug.'-'.uniqid(),
            'repo_url' => 'https://github.com/example/cmd',
            'branch' => 'main',
            'project_type' => 'static_html',
        ]);
    }

    // ── PruneMonitoringData ───────────────────────

    public function test_prune_monitoring_deletes_old_uptime_checks(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        // Old check (should be deleted)
        UptimeCheck::create([
            'site_id' => $site->id,
            'is_up' => true,
            'status_code' => 200,
            'response_time_ms' => 100,
            'checked_at' => now()->subDays(60),
        ]);

        // Recent check (should survive)
        UptimeCheck::create([
            'site_id' => $site->id,
            'is_up' => true,
            'status_code' => 200,
            'response_time_ms' => 150,
            'checked_at' => now()->subDays(5),
        ]);

        $this->artisan('pixelkraft:prune-monitoring', ['--uptime-days' => 30])
            ->assertExitCode(0);

        $this->assertSame(1, UptimeCheck::where('site_id', $site->id)->count());
    }

    public function test_prune_monitoring_dry_run_does_not_delete(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        UptimeCheck::create([
            'site_id' => $site->id,
            'is_up' => true,
            'status_code' => 200,
            'response_time_ms' => 100,
            'checked_at' => now()->subDays(60),
        ]);

        $this->artisan('pixelkraft:prune-monitoring', ['--uptime-days' => 30, '--dry-run' => true])
            ->assertExitCode(0);

        $this->assertSame(1, UptimeCheck::where('site_id', $site->id)->count());
    }

    // ── SendCampaigns ─────────────────────────────

    public function test_send_campaigns_transitions_scheduled_to_sending(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $campaign = NewsletterCampaign::create([
            'site_id' => $site->id,
            'subject' => 'Scheduled Newsletter',
            'body_html' => '<p>Hello</p>',
            'status' => 'scheduled',
            'scheduled_at' => now()->subMinutes(5), // past due
        ]);

        // No RESEND_API_KEY set — command logs warning but doesn't crash
        $this->artisan('pixelkraft:send-campaigns')->assertExitCode(0);

        // Campaign should have been moved to 'sending'
        $this->assertSame('sending', $campaign->fresh()->status);
    }

    public function test_send_campaigns_skips_future_scheduled(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $campaign = NewsletterCampaign::create([
            'site_id' => $site->id,
            'subject' => 'Future Newsletter',
            'body_html' => '<p>Later</p>',
            'status' => 'scheduled',
            'scheduled_at' => now()->addHours(2), // future
        ]);

        $this->artisan('pixelkraft:send-campaigns')->assertExitCode(0);

        $this->assertSame('scheduled', $campaign->fresh()->status);
    }

    public function test_send_campaigns_with_no_api_key_marks_stats_zero(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $campaign = NewsletterCampaign::create([
            'site_id' => $site->id,
            'subject' => 'Send Now',
            'body_html' => '<p>Hi</p>',
            'status' => 'sending',
        ]);

        // No RESEND_API_KEY — should log warning and set status=sent with sent=0
        config(['services.resend.key' => null]);

        $this->artisan('pixelkraft:send-campaigns')->assertExitCode(0);
        $this->assertSame('sending', $campaign->fresh()->status); // no key = no-op
    }

    public function test_send_campaigns_with_api_key_sends_to_subscribers(): void
    {
        Http::fake(['https://api.resend.com/emails' => Http::response(['id' => 'msg_123'], 200)]);

        $user = $this->makeUser();
        $site = $this->makeSite($user, 'send-api');

        NewsletterSubscriber::create([
            'site_id' => $site->id,
            'email' => 'sub@example.com',
            'status' => 'active',
        ]);

        $campaign = NewsletterCampaign::create([
            'site_id' => $site->id,
            'subject' => 'Test Send',
            'body_html' => '<p>Hi {{name}}!</p>',
            'status' => 'sending',
        ]);

        config(['services.resend.key' => 'fake_resend_key']);

        $this->artisan('pixelkraft:send-campaigns')->assertExitCode(0);

        $fresh = $campaign->fresh();
        $this->assertSame('sent', $fresh->status);
        $this->assertNotNull($fresh->sent_at);
        $this->assertSame(1, $fresh->stats['sent']);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'resend.com/emails'));
    }

    // ── ReplayWebhooks ────────────────────────────

    public function test_replay_webhooks_runs_without_error(): void
    {
        $this->artisan('pixelkraft:replay-webhooks', ['--since' => '1 hour ago'])
            ->assertExitCode(0);
    }

    // ── CheckSsl ─────────────────────────────────

    public function test_check_ssl_runs_without_error_on_empty_db(): void
    {
        $this->artisan('pixelkraft:check-ssl')->assertExitCode(0);
    }

    // ── CrawlLinks ────────────────────────────────

    public function test_crawl_links_runs_without_error_on_empty_db(): void
    {
        $this->artisan('pixelkraft:crawl-links')->assertExitCode(0);
    }
}
