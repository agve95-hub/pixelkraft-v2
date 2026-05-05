<?php

namespace Tests\Unit;

use App\Models\AnalyticsEvent;
use App\Models\AnalyticsSnapshot;
use App\Models\Page;
use App\Models\Site;
use App\Models\User;
use App\Services\AnalyticsAggregator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AnalyticsAggregatorTest extends TestCase
{
    use RefreshDatabase;

    private AnalyticsAggregator $aggregator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->aggregator = app(AnalyticsAggregator::class);
    }

    // ── Helpers ──────────────────────────────────

    private function makeUser(): User
    {
        return User::create([
            'name' => 'U',
            'email' => 'agg-'.uniqid().'@x.com',
            'password' => Hash::make('secret'),
            'role' => 'admin',
        ]);
    }

    private function makeSite(User $user): Site
    {
        return Site::create([
            'user_id' => $user->id,
            'name' => 'Test Site',
            'slug' => 'agg-'.uniqid(),
            'repo_url' => 'https://github.com/example/agg',
            'branch' => 'main',
            'project_type' => 'static_html',
        ]);
    }

    private function makePage(Site $site, string $path = '/'): Page
    {
        return Page::create([
            'site_id' => $site->id,
            'file_path' => ltrim($path, '/') ?: 'index.html',
            'url_path' => $path,
            'title' => 'Home',
        ]);
    }

    // ── summarizeSiteEvents ──────────────────────

    public function test_summarize_returns_zeros_when_no_events(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $result = $this->aggregator->summarizeSiteEvents($site, 30);

        $this->assertSame(0, $result['total_events']);
        $this->assertSame(0, $result['page_views']);
        $this->assertSame(0, $result['forms']);
        $this->assertSame(0, $result['interactions']);
        $this->assertSame([], $result['top_events']);
    }

    public function test_summarize_counts_page_views_correctly(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);
        $page = $this->makePage($site);

        AnalyticsEvent::create([
            'site_id' => $site->id,
            'page_id' => $page->id,
            'event_name' => 'page_view',
            'path' => '/',
            'visitor_id' => 'v1',
            'occurred_at' => now()->subDay(),
        ]);
        AnalyticsEvent::create([
            'site_id' => $site->id,
            'page_id' => $page->id,
            'event_name' => 'page_view',
            'path' => '/',
            'visitor_id' => 'v2',
            'occurred_at' => now()->subDay(),
        ]);
        AnalyticsEvent::create([
            'site_id' => $site->id,
            'page_id' => $page->id,
            'event_name' => 'form_submit',
            'path' => '/',
            'visitor_id' => 'v1',
            'occurred_at' => now()->subDay(),
        ]);

        $result = $this->aggregator->summarizeSiteEvents($site, 30);

        $this->assertSame(3, $result['total_events']);
        $this->assertSame(2, $result['page_views']);
        $this->assertSame(1, $result['forms']);
        $this->assertSame(0, $result['interactions']);
    }

    public function test_summarize_counts_interactions_for_custom_events(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);
        $page = $this->makePage($site);

        AnalyticsEvent::create([
            'site_id' => $site->id,
            'page_id' => $page->id,
            'event_name' => 'button_click',
            'path' => '/',
            'visitor_id' => 'v1',
            'occurred_at' => now()->subDay(),
        ]);

        $result = $this->aggregator->summarizeSiteEvents($site, 30);

        $this->assertSame(1, $result['total_events']);
        $this->assertSame(0, $result['page_views']);
        $this->assertSame(1, $result['interactions']);
    }

    public function test_summarize_returns_top_events_sorted_by_count(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);
        $page = $this->makePage($site);

        foreach (range(1, 3) as $i) {
            AnalyticsEvent::create([
                'site_id' => $site->id,
                'page_id' => $page->id,
                'event_name' => 'page_view',
                'path' => '/',
                'visitor_id' => "v{$i}",
                'occurred_at' => now()->subDay(),
            ]);
        }
        AnalyticsEvent::create([
            'site_id' => $site->id,
            'page_id' => $page->id,
            'event_name' => 'form_submit',
            'path' => '/',
            'visitor_id' => 'v1',
            'occurred_at' => now()->subDay(),
        ]);

        $result = $this->aggregator->summarizeSiteEvents($site, 30);

        $this->assertNotEmpty($result['top_events']);
        $this->assertSame('page_view', $result['top_events'][0]['event_name']);
        $this->assertSame(3, $result['top_events'][0]['count']);
    }

    public function test_summarize_excludes_events_older_than_window(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);
        $page = $this->makePage($site);

        AnalyticsEvent::create([
            'site_id' => $site->id,
            'page_id' => $page->id,
            'event_name' => 'page_view',
            'path' => '/',
            'visitor_id' => 'v1',
            'occurred_at' => now()->subDays(31),
        ]);

        $result = $this->aggregator->summarizeSiteEvents($site, 30);

        $this->assertSame(0, $result['total_events']);
    }

    // ── getSiteStats ─────────────────────────────

    public function test_get_site_stats_returns_zeros_when_no_pages(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $result = $this->aggregator->getSiteStats($site, 30);

        $this->assertSame(0, $result['total_visitors']);
        $this->assertSame(0, $result['total_pageviews']);
        $this->assertSame([], $result['daily']);
        $this->assertSame([], $result['top_pages']);
    }

    public function test_get_site_stats_aggregates_snapshots(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);
        $page = $this->makePage($site);

        AnalyticsSnapshot::create([
            'page_id' => $page->id,
            'date' => now()->subDays(3)->toDateString(),
            'source' => AnalyticsSnapshot::SOURCE_PIXELKRAFT_TRACKER,
            'visitors' => 100,
            'pageviews' => 150,
            'created_at' => now(),
        ]);
        AnalyticsSnapshot::create([
            'page_id' => $page->id,
            'date' => now()->subDays(2)->toDateString(),
            'source' => AnalyticsSnapshot::SOURCE_PIXELKRAFT_TRACKER,
            'visitors' => 50,
            'pageviews' => 80,
            'created_at' => now(),
        ]);

        $result = $this->aggregator->getSiteStats($site, 30);

        $this->assertSame(150, $result['total_visitors']);
        $this->assertSame(230, $result['total_pageviews']);
        $this->assertCount(2, $result['daily']);
    }

    public function test_get_site_stats_prefers_organic_snapshots(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);
        $page = $this->makePage($site);

        AnalyticsSnapshot::create([
            'page_id' => $page->id,
            'date' => now()->subDays(2)->toDateString(),
            'source' => AnalyticsSnapshot::SOURCE_GOOGLE_ORGANIC,
            'visitors' => 200,
            'pageviews' => 300,
            'created_at' => now(),
        ]);
        AnalyticsSnapshot::create([
            'page_id' => $page->id,
            'date' => now()->subDays(2)->toDateString(),
            'source' => AnalyticsSnapshot::SOURCE_PIXELKRAFT_TRACKER,
            'visitors' => 50,
            'pageviews' => 60,
            'created_at' => now(),
        ]);

        $result = $this->aggregator->getSiteStats($site, 30);

        $this->assertSame(200, $result['total_visitors']);
        $this->assertSame('Organic search (Google)', $result['traffic_label']);
    }

    public function test_get_site_stats_falls_back_to_all_sources_when_no_organic(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);
        $page = $this->makePage($site);

        AnalyticsSnapshot::create([
            'page_id' => $page->id,
            'date' => now()->subDays(2)->toDateString(),
            'source' => 'cloudflare',
            'visitors' => 75,
            'pageviews' => 90,
            'created_at' => now(),
        ]);

        $result = $this->aggregator->getSiteStats($site, 30);

        $this->assertSame(75, $result['total_visitors']);
        $this->assertSame('All sources', $result['traffic_label']);
    }

    // ── getPageStats ─────────────────────────────

    public function test_get_page_stats_aggregates_page_snapshots(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);
        $page = $this->makePage($site);

        AnalyticsSnapshot::create([
            'page_id' => $page->id,
            'date' => now()->subDays(5)->toDateString(),
            'source' => AnalyticsSnapshot::SOURCE_PIXELKRAFT_TRACKER,
            'visitors' => 40,
            'pageviews' => 55,
            'created_at' => now(),
        ]);

        $result = $this->aggregator->getPageStats($page, 30);

        $this->assertSame(40, $result['total_visitors']);
        $this->assertSame(55, $result['total_pageviews']);
        $this->assertCount(1, $result['daily']);
    }

    public function test_get_page_stats_returns_zeros_when_no_snapshots(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);
        $page = $this->makePage($site);

        $result = $this->aggregator->getPageStats($page, 30);

        $this->assertSame(0, $result['total_visitors']);
        $this->assertSame(0, $result['total_pageviews']);
        $this->assertSame([], $result['daily']);
    }
}
