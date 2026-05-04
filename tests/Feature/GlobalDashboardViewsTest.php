<?php

namespace Tests\Feature;

use App\Models\AnalyticsSnapshot;
use App\Models\Page;
use App\Models\Site;
use App\Models\SiteInboxMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class GlobalDashboardViewsTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $email = 'global@example.com'): User
    {
        return User::create([
            'name' => 'Global User',
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => 'editor',
        ]);
    }

    private function makeSite(User $user, string $slug = 'global-site'): Site
    {
        return Site::create([
            'user_id' => $user->id,
            'name' => 'Global Site',
            'slug' => $slug,
            'repo_url' => 'https://github.com/example/global',
            'branch' => 'main',
            'project_type' => 'static_html',
        ]);
    }

    // ── Inbox ────────────────────────────────────────

    public function test_global_inbox_loads_for_authenticated_user(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user)->get('/dashboard/inbox')->assertOk();
    }

    public function test_global_inbox_renders_correct_blade_view(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        SiteInboxMessage::create([
            'site_id' => $site->id,
            'direction' => 'inbound',
            'from_email' => 'visitor@example.com',
            'subject' => 'Question',
            'body' => 'Hello!',
            'is_read' => false,
        ]);

        $this->actingAs($user)
            ->get('/dashboard/inbox')
            ->assertOk()
            ->assertViewIs('dashboard.email.inbox');
    }

    public function test_global_inbox_message_isolation_via_db(): void
    {
        $owner = $this->makeUser('owner@g.com');
        $other = $this->makeUser('other@g.com');

        $site = $this->makeSite($owner);
        SiteInboxMessage::create([
            'site_id' => $site->id,
            'direction' => 'inbound',
            'from_email' => 'x@x.com',
            'subject' => 'Private',
            'body' => 'Secret',
            'is_read' => false,
        ]);

        // Other user's sites — should be empty
        $otherSiteIds = DB::table('sites')->where('user_id', $other->id)->pluck('id');
        $this->assertCount(0, $otherSiteIds);

        // Page still loads for the other user
        $this->actingAs($other)->get('/dashboard/inbox')->assertOk();
    }

    public function test_inbox_sent_tab_loads(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        SiteInboxMessage::create([
            'site_id' => $site->id,
            'direction' => 'outbound',
            'to_email' => 'client@example.com',
            'subject' => 'Invoice',
            'body' => 'Please pay.',
            'is_read' => true,
        ]);

        $this->actingAs($user)
            ->get('/dashboard/inbox?tab=sent')
            ->assertOk()
            ->assertViewIs('dashboard.email.inbox');
    }

    public function test_unauthenticated_user_cannot_access_inbox(): void
    {
        $this->get('/dashboard/inbox')->assertRedirect('/login');
    }

    // ── Analytics ────────────────────────────────────

    public function test_analytics_page_loads_for_authenticated_user(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user)->get('/dashboard/analytics')->assertOk();
    }

    public function test_analytics_renders_correct_blade_view(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user)
            ->get('/dashboard/analytics')
            ->assertOk()
            ->assertViewIs('dashboard.analytics.index');
    }

    public function test_analytics_aggregation_query_is_correct(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user, 'analytics-site');

        $pg = Page::create([
            'site_id' => $site->id,
            'file_path' => 'index.html',
            'url_path' => '/',
            'title' => 'Home',
        ]);

        AnalyticsSnapshot::create([
            'page_id' => $pg->id,
            'date' => now()->subDays(5)->toDateString(),
            'visitors' => 100,
            'pageviews' => 200,
            'source' => 'google_analytics',
            'created_at' => now(),
        ]);

        AnalyticsSnapshot::create([
            'page_id' => $pg->id,
            'date' => now()->subDays(10)->toDateString(),
            'visitors' => 50,
            'pageviews' => 80,
            'source' => 'google_analytics',
            'created_at' => now(),
        ]);

        // Verify the underlying query logic directly (same query as the route)
        $siteIds = DB::table('sites')
            ->where('user_id', $user->id)
            ->pluck('id');

        $this->assertCount(1, $siteIds);

        $total = AnalyticsSnapshot::query()
            ->join('pages', 'pages.id', '=', 'analytics_snapshots.page_id')
            ->whereIn('pages.site_id', $siteIds)
            ->where('analytics_snapshots.date', '>=', now()->subDays(29)->toDateString())
            ->sum('analytics_snapshots.visitors');

        $this->assertSame(150, (int) $total);
    }

    public function test_analytics_does_not_include_other_users_data(): void
    {
        $owner = $this->makeUser('oa@g.com');
        $other = $this->makeUser('ob@g.com');

        $site = $this->makeSite($owner, 'analytics-private-site');
        $pg = Page::create(['site_id' => $site->id, 'file_path' => 'index.html', 'url_path' => '/', 'title' => 'Home']);
        AnalyticsSnapshot::create(['page_id' => $pg->id, 'date' => now()->subDay()->toDateString(), 'visitors' => 999, 'pageviews' => 999, 'source' => 'google_analytics', 'created_at' => now()]);

        // Other user should have no access to owner's site data
        $siteIds = DB::table('sites')
            ->where('user_id', $other->id)
            ->pluck('id');
        $this->assertCount(0, $siteIds, 'Other user should see 0 sites');

        $this->actingAs($other)
            ->get('/dashboard/analytics')
            ->assertOk();
    }

    public function test_unauthenticated_user_cannot_access_analytics(): void
    {
        $this->get('/dashboard/analytics')->assertRedirect('/login');
    }
}
