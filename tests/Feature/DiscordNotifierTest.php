<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Site;
use App\Models\User;
use App\Services\DiscordNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DiscordNotifierTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(bool $hasWebhook = true): User
    {
        $user = User::create([
            'name' => 'A', 'email' => 'dn-'.uniqid().'@x.com',
            'password' => Hash::make('secret'), 'role' => 'admin',
        ]);

        if ($hasWebhook) {
            $user->forceFill(['discord_webhook' => 'https://discord.com/api/webhooks/test/token'])->save();
        }

        return $user;
    }

    public function test_sends_to_admin_discord_webhook_on_global_notification(): void
    {
        Http::fake(['https://discord.com/*' => Http::response('', 204)]);
        $admin = $this->makeAdmin();

        $notification = Notification::create([
            'type' => 'deploy_failed', 'title' => 'Test failure',
            'body' => 'Something broke.', 'is_read' => false,
            'created_at' => now(),
        ]);

        app(DiscordNotifier::class)->send($notification);

        Http::assertSentCount(1);
    }

    public function test_does_not_send_when_no_webhook_configured(): void
    {
        Http::fake();
        $this->makeAdmin(hasWebhook: false);

        $notification = Notification::create([
            'type' => 'deploy_failed', 'title' => 'Test', 'is_read' => false,
            'created_at' => now(),
        ]);

        app(DiscordNotifier::class)->send($notification);

        Http::assertNothingSent();
    }

    public function test_sends_to_site_owner_for_site_scoped_notification(): void
    {
        Http::fake(['https://discord.com/*' => Http::response('', 204)]);
        $owner = $this->makeAdmin();
        $site = Site::create([
            'user_id' => $owner->id, 'name' => 'S', 'slug' => 'dn-'.uniqid(),
            'branch' => 'main', 'project_type' => 'static_html',
        ]);

        $notification = Notification::create([
            'type' => 'uptime_down', 'title' => 'Site down', 'site_id' => $site->id,
            'is_read' => false, 'created_at' => now(),
        ]);

        app(DiscordNotifier::class)->send($notification);

        Http::assertSentCount(1);
    }

    public function test_discord_failure_does_not_throw(): void
    {
        Http::fake(['https://discord.com/*' => Http::response('', 500)]);
        $this->makeAdmin();

        $notification = Notification::create([
            'type' => 'deploy_failed', 'title' => 'Test', 'is_read' => false,
            'created_at' => now(),
        ]);

        app(DiscordNotifier::class)->send($notification); // must not throw
        $this->assertTrue(true);
    }

    public function test_notification_create_alert_fires_discord(): void
    {
        Http::fake(['https://discord.com/*' => Http::response('', 204)]);
        $this->makeAdmin();

        Notification::createAlert(type: 'deploy_failed', title: 'Deploy broke');

        Http::assertSentCount(1);
    }
}
