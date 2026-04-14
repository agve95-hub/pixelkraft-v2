<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_editor_only_sees_notifications_for_visible_sites(): void
    {
        $alice = User::create([
            'name' => 'Alice',
            'email' => 'alice-notif@example.com',
            'password' => Hash::make('password'),
            'role' => 'editor',
        ]);

        $bob = User::create([
            'name' => 'Bob',
            'email' => 'bob-notif@example.com',
            'password' => Hash::make('password'),
            'role' => 'editor',
        ]);

        $aliceSite = Site::create([
            'user_id' => $alice->id,
            'name' => 'Alice Co',
            'slug' => 'alice-co',
            'repo_url' => 'https://github.com/example/ac.git',
            'branch' => 'main',
        ]);

        $bobSite = Site::create([
            'user_id' => $bob->id,
            'name' => 'Bob Co',
            'slug' => 'bob-co',
            'repo_url' => 'https://github.com/example/bc.git',
            'branch' => 'main',
        ]);

        Notification::create([
            'type' => 'form_received',
            'title' => 'Alice alert',
            'site_id' => $aliceSite->id,
            'is_read' => false,
            'created_at' => now(),
        ]);

        Notification::create([
            'type' => 'form_received',
            'title' => 'Bob alert',
            'site_id' => $bobSite->id,
            'is_read' => false,
            'created_at' => now(),
        ]);

        Sanctum::actingAs($alice);

        $data = $this->getJson('/api/v1/notifications')->assertOk()->json('data');
        $this->assertCount(1, $data);
        $this->assertSame('Alice alert', $data[0]['title']);
    }

    public function test_mark_read_updates_notification(): void
    {
        $user = User::create([
            'name' => 'U',
            'email' => 'u-notif@example.com',
            'password' => Hash::make('password'),
            'role' => 'editor',
        ]);

        $site = Site::create([
            'user_id' => $user->id,
            'name' => 'S',
            'slug' => 's-notif',
            'repo_url' => 'https://github.com/example/sn.git',
            'branch' => 'main',
        ]);

        $n = Notification::create([
            'type' => 'deploy_failed',
            'title' => 'Deploy failed',
            'site_id' => $site->id,
            'is_read' => false,
            'created_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/notifications/{$n->id}/read")
            ->assertOk()
            ->assertJson(['status' => 'ok']);

        $this->assertTrue($n->fresh()->is_read);
    }

    public function test_mark_all_read_for_editor_sites_only(): void
    {
        $alice = User::create([
            'name' => 'A2',
            'email' => 'a2-notif@example.com',
            'password' => Hash::make('password'),
            'role' => 'editor',
        ]);

        $bob = User::create([
            'name' => 'B2',
            'email' => 'b2-notif@example.com',
            'password' => Hash::make('password'),
            'role' => 'editor',
        ]);

        $aliceSite = Site::create([
            'user_id' => $alice->id,
            'name' => 'A2 Co',
            'slug' => 'a2-co',
            'repo_url' => 'https://github.com/example/a2.git',
            'branch' => 'main',
        ]);

        $bobSite = Site::create([
            'user_id' => $bob->id,
            'name' => 'B2 Co',
            'slug' => 'b2-co',
            'repo_url' => 'https://github.com/example/b2.git',
            'branch' => 'main',
        ]);

        $aliceUnread = Notification::create([
            'type' => 'uptime_down',
            'title' => 'Alice unread',
            'site_id' => $aliceSite->id,
            'is_read' => false,
            'created_at' => now(),
        ]);

        $bobUnread = Notification::create([
            'type' => 'uptime_down',
            'title' => 'Bob unread',
            'site_id' => $bobSite->id,
            'is_read' => false,
            'created_at' => now(),
        ]);

        Sanctum::actingAs($alice);

        $this->postJson('/api/v1/notifications/read-all')->assertOk();

        $this->assertTrue($aliceUnread->fresh()->is_read);
        $this->assertFalse($bobUnread->fresh()->is_read);
    }
}
