<?php

namespace Tests\Feature;

use App\Enums\DeployStatus;
use App\Models\AnalyticsSnapshot;
use App\Models\Page;
use App\Models\Site;
use App\Models\UptimeCheck;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SiteAnalyticsControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role = 'admin'): User
    {
        return User::create([
            'name' => 'U',
            'email' => 'sac-'.uniqid().'@x.com',
            'password' => Hash::make('secret'),
            'role' => $role,
        ]);
    }

    private function makeSite(User $user, array $attrs = []): Site
    {
        return Site::create(array_merge([
            'user_id' => $user->id,
            'name' => 'Analytics Site',
            'slug' => 'sac-'.uniqid(),
            'repo_url' => 'https://github.com/example/sac',
            'branch' => 'main',
            'project_type' => 'static_html',
            'deploy_status' => DeployStatus::Live,
        ], $attrs));
    }

    private function makePage(Site $site, string $path = '/'): Page
    {
        return Page::create([
            'site_id' => $site->id,
            'file_path' => 'index.html',
            'url_path' => $path,
            'title' => 'Home',
            'is_published' => true,
        ]);
    }

    // ── authentication ───────────────────────────

    public function test_unauthenticated_request_redirects_to_login(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $this->get(route('sites.analytics', $site))
            ->assertRedirect(route('login'));
    }

    public function test_owner_can_load_analytics_page(): void
    {
        $user = $this->makeUser('editor');
        $site = $this->makeSite($user);

        $this->actingAs($user)
            ->get(route('sites.analytics', $site))
            ->assertOk()
            ->assertViewIs('dashboard.sites.analytics');
    }

    public function test_admin_can_load_any_site_analytics(): void
    {
        $owner = $this->makeUser('editor');
        $site = $this->makeSite($owner);
        $admin = $this->makeUser('admin');

        $this->actingAs($admin)
            ->get(route('sites.analytics', $site))
            ->assertOk();
    }

    public function test_other_user_cannot_access_analytics(): void
    {
        $owner = $this->makeUser('editor');
        $site = $this->makeSite($owner);
        $other = $this->makeUser('editor');

        $this->actingAs($other)
            ->get(route('sites.analytics', $site))
            ->assertNotFound();
    }

    // ── view data ────────────────────────────────

    public function test_response_includes_site_in_view(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $response = $this->actingAs($user)
            ->get(route('sites.analytics', $site));

        $response->assertViewHas('site', fn ($s) => $s->id === $site->id);
    }

    public function test_response_includes_traffic_total_key(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $response = $this->actingAs($user)
            ->get(route('sites.analytics', $site));

        $response->assertViewHas('trafficTotal');
    }

    public function test_response_includes_uptime_percent_key(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $response = $this->actingAs($user)
            ->get(route('sites.analytics', $site));

        $response->assertViewHas('uptimePercent');
    }

    public function test_uptime_defaults_to_100_when_no_checks(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $response = $this->actingAs($user)
            ->get(route('sites.analytics', $site));

        $response->assertViewHas('uptimePercent', 100.0);
    }

    public function test_uptime_calculated_from_checks(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        UptimeCheck::create(['site_id' => $site->id, 'is_up' => true, 'status_code' => 200, 'response_time_ms' => 100, 'checked_at' => now()->subHour()]);
        UptimeCheck::create(['site_id' => $site->id, 'is_up' => false, 'status_code' => 503, 'response_time_ms' => 0, 'checked_at' => now()->subHour()]);

        $response = $this->actingAs($user)
            ->get(route('sites.analytics', $site));

        // 1 of 2 up = 50%
        $response->assertViewHas('uptimePercent', 50.0);
    }

    public function test_traffic_total_sums_snapshots(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);
        $page = $this->makePage($site);

        AnalyticsSnapshot::create([
            'page_id' => $page->id,
            'date' => now()->subDays(2)->toDateString(),
            'source' => 'platform_tracker',
            'visitors' => 120,
            'pageviews' => 180,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->get(route('sites.analytics', $site));

        $response->assertViewHas('trafficTotal', 120);
    }

    public function test_response_includes_daily_bars_for_uptime(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $response = $this->actingAs($user)
            ->get(route('sites.analytics', $site));

        $response->assertViewHas('dailyBars');
        $this->assertCount(30, $response->viewData('dailyBars'));
    }

    public function test_response_includes_traffic_chart(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $response = $this->actingAs($user)
            ->get(route('sites.analytics', $site));

        $response->assertViewHas('trafficChart');
        $chart = $response->viewData('trafficChart');
        $this->assertArrayHasKey('line_path', $chart);
        $this->assertArrayHasKey('area_path', $chart);
    }
}
