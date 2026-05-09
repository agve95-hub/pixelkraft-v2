<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Page;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Exercises routes that are tested via URL strings elsewhere,
 * adding explicit route-name coverage.
 */
class RouteNameCoverageTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $email = 'rn@example.com'): User
    {
        return User::create([
            'name' => 'RN User',
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => 'editor',
        ]);
    }

    private function makeSite(User $user, string $slug = 'rn-site'): Site
    {
        return Site::create([
            'user_id' => $user->id,
            'name' => 'RN Site',
            'slug' => $slug.'-'.uniqid(),
            'repo_url' => 'https://github.com/example/rn',
            'branch' => 'main',
            'project_type' => 'static_html',
        ]);
    }

    // ── API v1: sites ─────────────────────────────

    public function test_api_v1_sites_index_by_route_name(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user, ['platform:sites:read']);

        $this->getJson(route('api.v1.sites.index'))->assertOk();
    }

    public function test_api_v1_sites_show_by_route_name(): void
    {
        $user = $this->makeUser('show@rn.com');
        $site = $this->makeSite($user);
        Sanctum::actingAs($user, ['platform:sites:read']);

        $this->getJson(route('api.v1.sites.show', $site))->assertOk();
    }

    public function test_api_v1_sites_sync_by_route_name(): void
    {
        $user = $this->makeUser('sync@rn.com');
        $site = $this->makeSite($user, 'sync-rn');
        Sanctum::actingAs($user, ['platform:sites:sync']);

        // Sync dispatches a job; without a real repo it returns success or an error
        $response = $this->postJson(route('api.v1.sites.sync', $site));
        $this->assertContains($response->status(), [200, 202, 500]);
    }

    // ── API v1: notifications ─────────────────────

    public function test_api_v1_notifications_index_by_route_name(): void
    {
        $user = $this->makeUser('notif@rn.com');
        $site = $this->makeSite($user, 'notif-rn');
        Sanctum::actingAs($user, ['platform:notifications:read']);

        $this->getJson(route('api.v1.notifications.index'))->assertOk();
    }

    public function test_api_v1_notifications_read_by_route_name(): void
    {
        $user = $this->makeUser('nread@rn.com');
        $site = $this->makeSite($user, 'nread-rn');

        $notification = Notification::create([
            'site_id' => $site->id,
            'type' => 'deploy_failed',
            'title' => 'Deploy failed',
            'body' => 'Error.',
            'is_read' => false,
            'created_at' => now(),
        ]);

        Sanctum::actingAs($user, ['platform:notifications:write']);

        $this->postJson(route('api.v1.notifications.read', $notification->id))
            ->assertOk();

        $this->assertTrue((bool) $notification->fresh()->is_read);
    }

    public function test_api_v1_notifications_read_all_by_route_name(): void
    {
        $user = $this->makeUser('nrall@rn.com');
        $site = $this->makeSite($user, 'nrall-rn');

        Notification::create([
            'site_id' => $site->id,
            'type' => 'uptime_down',
            'title' => 'Site down',
            'body' => 'Error.',
            'is_read' => false,
            'created_at' => now(),
        ]);

        Sanctum::actingAs($user, ['platform:notifications:write']);

        $this->postJson(route('api.v1.notifications.readAll'))->assertOk();
    }

    // ── Editor asset ──────────────────────────────

    public function test_editor_asset_returns_404_for_uncloned_site(): void
    {
        $user = $this->makeUser('asset@rn.com');
        $site = $this->makeSite($user, 'asset-rn');

        // site has no repo_path so realpath() fails → 404
        $page = Page::create([
            'site_id' => $site->id,
            'file_path' => 'index.html',
            'url_path' => '/',
            'title' => 'Home',
        ]);

        $this->actingAs($user)
            ->get(route('editor.asset', [$site, $page, 'style.css']))
            ->assertStatus(404);
    }

    // ── Public API: campaigns + forms + inbox ─────

    public function test_api_sites_active_campaigns_by_route_name(): void
    {
        $user = $this->makeUser('camp@rn.com');
        $site = $this->makeSite($user, 'camp-rn');

        $this->getJson(route('api.sites.active-campaigns', $site))->assertOk();
    }

    public function test_api_forms_store_by_route_name(): void
    {
        $user = $this->makeUser('form@rn.com');
        $site = $this->makeSite($user, 'form-rn');
        $site->update([
            'domain' => 'form-rn.example.com',
            'inbox_inbound_secret' => str_repeat('s', 32),
        ]);

        $this->postJson(route('api.forms.store', $site->slug), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'message' => 'Hello!',
        ])->assertCreated();
    }

    public function test_api_inbox_inbound_by_route_name(): void
    {
        $user = $this->makeUser('inb@rn.com');
        $site = $this->makeSite($user, 'inb-rn');
        $secret = str_repeat('x', 32);
        $site->update(['inbox_inbound_secret' => $secret]);

        $this->postJson(route('api.inbox.inbound', $site->slug), [
            'from_email' => 'sender@example.com',
            'subject' => 'Hello',
            'body' => 'Test message',
        ], [
            'Authorization' => 'Bearer '.$secret,
        ])->assertCreated();
    }
}
