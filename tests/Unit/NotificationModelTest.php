<?php

namespace Tests\Unit;

use App\Models\Notification;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class NotificationModelTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::create([
            'name' => 'U',
            'email' => 'notif-'.uniqid().'@x.com',
            'password' => Hash::make('secret'),
            'role' => 'admin',
        ]);
    }

    private function makeSite(User $user): Site
    {
        return Site::create([
            'user_id' => $user->id,
            'name' => 'S',
            'slug' => 'notif-'.uniqid(),
            'repo_url' => 'https://github.com/example/notif',
            'branch' => 'main',
            'project_type' => 'static_html',
        ]);
    }

    // ── createAlert ──────────────────────────────

    public function test_create_alert_persists_to_database(): void
    {
        Notification::createAlert(
            type: 'deploy_failed',
            title: 'Deploy failed',
        );

        $this->assertDatabaseHas('notifications', [
            'type' => 'deploy_failed',
            'title' => 'Deploy failed',
        ]);
    }

    public function test_create_alert_returns_notification_instance(): void
    {
        $notification = Notification::createAlert(
            type: 'ssl_expiring',
            title: 'SSL expiring soon',
        );

        $this->assertInstanceOf(Notification::class, $notification);
        $this->assertNotNull($notification->id);
    }

    public function test_create_alert_stores_all_fields(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $notification = Notification::createAlert(
            type: 'deploy_failed',
            title: 'Build failed',
            body: 'The build command exited with code 1.',
            siteId: $site->id,
            data: ['exit_code' => 1, 'stage' => 'build'],
        );

        $this->assertSame('deploy_failed', $notification->type);
        $this->assertSame('Build failed', $notification->title);
        $this->assertSame('The build command exited with code 1.', $notification->body);
        $this->assertSame($site->id, $notification->site_id);
        $this->assertSame(1, $notification->data['exit_code']);
        $this->assertSame('build', $notification->data['stage']);
    }

    public function test_create_alert_without_site_id(): void
    {
        $notification = Notification::createAlert(
            type: 'system',
            title: 'System alert',
        );

        $this->assertNull($notification->site_id);
        $this->assertDatabaseHas('notifications', ['type' => 'system', 'site_id' => null]);
    }

    public function test_create_alert_sets_created_at(): void
    {
        $notification = Notification::createAlert(
            type: 'deploy_failed',
            title: 'Test',
        );

        $this->assertNotNull($notification->created_at);
    }

    public function test_multiple_alerts_can_be_created(): void
    {
        Notification::createAlert(type: 'a', title: 'First');
        Notification::createAlert(type: 'b', title: 'Second');
        Notification::createAlert(type: 'c', title: 'Third');

        $this->assertSame(3, Notification::count());
    }

    public function test_is_read_defaults_to_false(): void
    {
        $notification = Notification::createAlert(type: 'test', title: 'Test');

        $this->assertFalse((bool) $notification->is_read);
    }
}
