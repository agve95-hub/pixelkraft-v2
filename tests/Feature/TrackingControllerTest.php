<?php

namespace Tests\Feature;

use App\Models\AnalyticsEvent;
use App\Models\Page;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrackingControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_tracker_script_is_served_for_active_site(): void
    {
        $site = Site::create([
            'name' => 'Tracker Site',
            'slug' => 'tracker-site',
            'repo_url' => 'https://github.com/acme/tracker.git',
            'branch' => 'main',
            'is_active' => true,
        ]);

        $response = $this->get(route('tracking.script', ['site' => $site]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/javascript; charset=utf-8');
        $response->assertSee('window.pixelkraftTrack', false);
        $response->assertSee((string) route('tracking.collect', ['site' => $site]), false);
    }

    public function test_tracking_collect_stores_analytics_event_and_matches_page(): void
    {
        $site = Site::create([
            'name' => 'Tracked Site',
            'slug' => 'tracked-site',
            'repo_url' => 'https://github.com/acme/tracked.git',
            'branch' => 'main',
            'is_active' => true,
        ]);

        $page = Page::create([
            'site_id' => $site->id,
            'file_path' => 'index.html',
            'url_path' => '/about',
            'title' => 'About',
            'content_hash' => 'hash',
        ]);

        $payload = [
            'event_name' => 'cta_click',
            'path' => '/about?ref=campaign',
            'referrer' => 'https://google.com',
            'visitor_id' => 'visitor-1',
            'session_id' => 'session-1',
            'payload' => [
                'label' => 'Start project',
            ],
        ];

        $this->postJson(route('tracking.collect', ['site' => $site]), $payload)
            ->assertStatus(202)
            ->assertJson(['status' => 'ok']);

        $event = AnalyticsEvent::query()->first();

        $this->assertNotNull($event);
        $this->assertSame($site->id, $event->site_id);
        $this->assertSame($page->id, $event->page_id);
        $this->assertSame('cta_click', $event->event_name);
        $this->assertSame('/about?ref=campaign', $event->path);
        $this->assertSame('Start project', $event->payload['label']);
    }
}
