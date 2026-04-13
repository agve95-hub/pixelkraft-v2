<?php

namespace Tests\Feature;

use App\Livewire\Dashboard\ActivityFeed;
use App\Livewire\Dashboard\SeoIssuesPanel;
use App\Livewire\Layout\NotificationBell;
use App\Models\DeployLog;
use App\Models\Notification;
use App\Models\Page;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardWidgetsTenancyTest extends TestCase
{
    use RefreshDatabase;

    public function test_activity_feed_only_shows_deploy_logs_for_visible_sites(): void
    {
        $alice = User::create([
            'name' => 'Alice',
            'email' => 'alice-widgets@example.com',
            'password' => Hash::make('password'),
            'role' => 'editor',
        ]);

        $bob = User::create([
            'name' => 'Bob',
            'email' => 'bob-widgets@example.com',
            'password' => Hash::make('password'),
            'role' => 'editor',
        ]);

        $aliceSite = Site::create([
            'user_id' => $alice->id,
            'name' => 'Alice Project Alpha',
            'slug' => 'alice-alpha',
            'repo_url' => 'https://github.com/example/a',
            'branch' => 'main',
            'project_type' => 'static_html',
        ]);

        $bobSite = Site::create([
            'user_id' => $bob->id,
            'name' => 'Bob Secret Deploy',
            'slug' => 'bob-secret-deploy',
            'repo_url' => 'https://github.com/example/b',
            'branch' => 'main',
            'project_type' => 'static_html',
        ]);

        DeployLog::create([
            'site_id' => $aliceSite->id,
            'status' => 'success',
            'commit_sha' => 'aaa1111',
            'commit_message' => 'Alice deploy',
            'duration_ms' => 1000,
            'triggered_by' => 'manual',
            'created_at' => now()->subMinute(),
        ]);

        DeployLog::create([
            'site_id' => $bobSite->id,
            'status' => 'success',
            'commit_sha' => 'bbb2222',
            'commit_message' => 'Bob deploy',
            'duration_ms' => 2000,
            'triggered_by' => 'manual',
            'created_at' => now(),
        ]);

        Livewire::actingAs($alice)
            ->test(ActivityFeed::class)
            ->assertSee('Alice Project Alpha', false)
            ->assertDontSee('Bob Secret Deploy', false);
    }

    public function test_notification_bell_scopes_unread_and_list_to_visible_sites(): void
    {
        $alice = User::create([
            'name' => 'Alice',
            'email' => 'alice-bell@example.com',
            'password' => Hash::make('password'),
            'role' => 'editor',
        ]);

        $bob = User::create([
            'name' => 'Bob',
            'email' => 'bob-bell@example.com',
            'password' => Hash::make('password'),
            'role' => 'editor',
        ]);

        $aliceSite = Site::create([
            'user_id' => $alice->id,
            'name' => 'Alice Bell Site',
            'slug' => 'alice-bell-site',
            'repo_url' => 'https://github.com/example/ab',
            'branch' => 'main',
            'project_type' => 'static_html',
        ]);

        $bobSite = Site::create([
            'user_id' => $bob->id,
            'name' => 'Bob Bell Site',
            'slug' => 'bob-bell-site',
            'repo_url' => 'https://github.com/example/bb',
            'branch' => 'main',
            'project_type' => 'static_html',
        ]);

        Notification::create([
            'type' => 'deploy_failed',
            'title' => 'Alice failure',
            'body' => 'Details',
            'site_id' => $aliceSite->id,
            'is_read' => false,
            'created_at' => now(),
        ]);

        Notification::create([
            'type' => 'deploy_failed',
            'title' => 'Bob failure',
            'body' => 'Secret',
            'site_id' => $bobSite->id,
            'is_read' => false,
            'created_at' => now(),
        ]);

        Livewire::actingAs($alice)
            ->test(NotificationBell::class)
            ->assertSet('unreadCount', 1)
            ->assertSee('Alice failure', false)
            ->assertDontSee('Bob failure', false);
    }

    public function test_seo_issues_panel_only_lists_pages_from_visible_sites(): void
    {
        $alice = User::create([
            'name' => 'Alice',
            'email' => 'alice-seo@example.com',
            'password' => Hash::make('password'),
            'role' => 'editor',
        ]);

        $bob = User::create([
            'name' => 'Bob',
            'email' => 'bob-seo@example.com',
            'password' => Hash::make('password'),
            'role' => 'editor',
        ]);

        $aliceSite = Site::create([
            'user_id' => $alice->id,
            'name' => 'Alice SEO Site',
            'slug' => 'alice-seo',
            'repo_url' => 'https://github.com/example/as',
            'branch' => 'main',
            'project_type' => 'static_html',
        ]);

        $bobSite = Site::create([
            'user_id' => $bob->id,
            'name' => 'Bob SEO Site',
            'slug' => 'bob-seo',
            'repo_url' => 'https://github.com/example/bs',
            'branch' => 'main',
            'project_type' => 'static_html',
        ]);

        Page::create([
            'site_id' => $aliceSite->id,
            'file_path' => 'a.html',
            'url_path' => '/a',
            'title' => 'Alice page',
            'meta_description' => null,
            'is_published' => true,
        ]);

        Page::create([
            'site_id' => $bobSite->id,
            'file_path' => 'b.html',
            'url_path' => '/b',
            'title' => 'Bob page',
            'meta_description' => null,
            'is_published' => true,
        ]);

        Livewire::actingAs($alice)
            ->test(SeoIssuesPanel::class)
            ->assertSee('Alice SEO Site', false)
            ->assertDontSee('Bob SEO Site', false);
    }
}
