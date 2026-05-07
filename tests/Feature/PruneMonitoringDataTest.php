<?php

namespace Tests\Feature;

use App\Models\AnalyticsEvent;
use App\Models\Page;
use App\Models\Site;
use App\Models\UptimeCheck;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PruneMonitoringDataTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::create([
            'name' => 'U',
            'email' => 'pmd-'.uniqid().'@x.com',
            'password' => Hash::make('secret'),
            'role' => 'admin',
        ]);
    }

    private function makeSite(User $user): Site
    {
        return Site::create([
            'user_id' => $user->id,
            'name' => 'S',
            'slug' => 'pmd-'.uniqid(),
            'repo_url' => 'https://github.com/example/pmd',
            'branch' => 'main',
            'project_type' => 'static_html',
        ]);
    }

    private function makePage(Site $site): Page
    {
        return Page::create([
            'site_id' => $site->id,
            'file_path' => 'index.html',
            'url_path' => '/',
            'title' => 'Home',
        ]);
    }

    private function makeUptimeCheck(Site $site, string $checkedAt): UptimeCheck
    {
        return UptimeCheck::create([
            'site_id' => $site->id,
            'is_up' => true,
            'status_code' => 200,
            'response_time_ms' => 100,
            'checked_at' => $checkedAt,
        ]);
    }

    private function makeAnalyticsEvent(Site $site, Page $page, string $createdAt): AnalyticsEvent
    {
        $event = AnalyticsEvent::create([
            'site_id' => $site->id,
            'page_id' => $page->id,
            'event_name' => 'page_view',
            'path' => '/',
            'visitor_id' => uniqid(),
            'occurred_at' => $createdAt,
        ]);

        // created_at is not in $fillable — update via query builder to bypass the guard
        DB::table('analytics_events')
            ->where('id', $event->id)
            ->update(['created_at' => $createdAt]);

        return $event;
    }

    // ── uptime_checks pruning ────────────────────

    public function test_deletes_uptime_checks_older_than_retention_window(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $old = $this->makeUptimeCheck($site, now()->subDays(31)->toDateTimeString());
        $recent = $this->makeUptimeCheck($site, now()->subDays(1)->toDateTimeString());

        $this->artisan('pixelkraft:prune-monitoring', ['--uptime-days' => 30])
            ->assertSuccessful();

        $this->assertDatabaseMissing('uptime_checks', ['id' => $old->id]);
        $this->assertDatabaseHas('uptime_checks', ['id' => $recent->id]);
    }

    public function test_deletes_analytics_events_older_than_retention_window(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);
        $page = $this->makePage($site);

        $old = $this->makeAnalyticsEvent($site, $page, now()->subDays(91)->toDateTimeString());
        $recent = $this->makeAnalyticsEvent($site, $page, now()->subDays(1)->toDateTimeString());

        $this->artisan('pixelkraft:prune-monitoring', ['--events-days' => 90])
            ->assertSuccessful();

        $this->assertDatabaseMissing('analytics_events', ['id' => $old->id]);
        $this->assertDatabaseHas('analytics_events', ['id' => $recent->id]);
    }

    // ── dry-run ──────────────────────────────────

    public function test_dry_run_does_not_delete_uptime_checks(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);
        $old = $this->makeUptimeCheck($site, now()->subDays(60)->toDateTimeString());

        $this->artisan('pixelkraft:prune-monitoring', ['--uptime-days' => 30, '--dry-run' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('[dry-run]');

        $this->assertDatabaseHas('uptime_checks', ['id' => $old->id]);
    }

    public function test_dry_run_does_not_delete_analytics_events(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);
        $page = $this->makePage($site);
        $old = $this->makeAnalyticsEvent($site, $page, now()->subDays(120)->toDateTimeString());

        $this->artisan('pixelkraft:prune-monitoring', ['--events-days' => 90, '--dry-run' => true])
            ->assertSuccessful();

        $this->assertDatabaseHas('analytics_events', ['id' => $old->id]);
    }

    // ── validation ───────────────────────────────

    public function test_fails_when_retention_days_is_zero(): void
    {
        $this->artisan('pixelkraft:prune-monitoring', ['--uptime-days' => 0])
            ->assertFailed();
    }

    public function test_custom_retention_windows_are_respected(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $shouldDelete = $this->makeUptimeCheck($site, now()->subDays(8)->toDateTimeString());
        $shouldKeep = $this->makeUptimeCheck($site, now()->subDays(3)->toDateTimeString());

        $this->artisan('pixelkraft:prune-monitoring', ['--uptime-days' => 7])
            ->assertSuccessful();

        $this->assertDatabaseMissing('uptime_checks', ['id' => $shouldDelete->id]);
        $this->assertDatabaseHas('uptime_checks', ['id' => $shouldKeep->id]);
    }

    // ── no-op when nothing to prune ──────────────

    public function test_succeeds_when_nothing_to_prune(): void
    {
        $this->artisan('pixelkraft:prune-monitoring')->assertSuccessful();
    }
}
