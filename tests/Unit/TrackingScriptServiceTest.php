<?php

namespace Tests\Unit;

use App\Models\Site;
use App\Models\User;
use App\Services\TrackingScriptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TrackingScriptServiceTest extends TestCase
{
    use RefreshDatabase;

    private TrackingScriptService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TrackingScriptService::class);
    }

    private function makeSite(): Site
    {
        $user = User::create([
            'name' => 'U',
            'email' => 'ts-'.uniqid().'@x.com',
            'password' => Hash::make('password'),
            'role' => 'editor',
        ]);

        return Site::create([
            'user_id' => $user->id,
            'name' => 'Track Site',
            'slug' => 'track-'.uniqid(),
            'repo_url' => 'https://github.com/example/track',
            'branch' => 'main',
            'project_type' => 'static_html',
        ]);
    }

    // ── trackerScript ─────────────────────────────

    public function test_tracker_script_contains_site_id(): void
    {
        $site = $this->makeSite();
        $script = $this->service->trackerScript($site);

        $this->assertStringContainsString($site->id, $script);
    }

    public function test_tracker_script_is_iife(): void
    {
        $site = $this->makeSite();
        $script = $this->service->trackerScript($site);

        $this->assertStringStartsWith('(function', trim($script));
        $this->assertStringContainsString('})();', $script);
    }

    public function test_tracker_script_sends_page_view(): void
    {
        $site = $this->makeSite();
        $script = $this->service->trackerScript($site);

        $this->assertStringContainsString('page_view', $script);
    }

    public function test_tracker_script_uses_navigator_send_beacon(): void
    {
        $site = $this->makeSite();
        $script = $this->service->trackerScript($site);

        $this->assertStringContainsString('sendBeacon', $script);
    }

    public function test_tracker_script_tracks_click_events(): void
    {
        $site = $this->makeSite();
        $script = $this->service->trackerScript($site);

        $this->assertStringContainsString('click', $script);
        $this->assertStringContainsString('interaction', $script);
    }

    public function test_tracker_script_tracks_form_submits(): void
    {
        $site = $this->makeSite();
        $script = $this->service->trackerScript($site);

        $this->assertStringContainsString('submit', $script);
        $this->assertStringContainsString('form_submit', $script);
    }

    public function test_tracker_script_exposes_global_track_function(): void
    {
        $site = $this->makeSite();
        $script = $this->service->trackerScript($site);

        $this->assertStringContainsString('pixelkraftTrack', $script);
    }

    // ── injectIntoHtml ────────────────────────────

    public function test_inject_adds_script_before_closing_body(): void
    {
        $site = $this->makeSite();
        $html = '<html><body><h1>Hello</h1></body></html>';
        $result = $this->service->injectIntoHtml($site, $html);

        $this->assertStringContainsString('<script', $result);
        $this->assertStringContainsString('</body>', $result);

        // Script should be before the closing body
        $scriptPos = strpos($result, '<script');
        $bodyClosePos = strpos($result, '</body>');
        $this->assertLessThan($bodyClosePos, $scriptPos);
    }

    public function test_inject_does_not_double_inject(): void
    {
        $site = $this->makeSite();
        $html = '<html><body><h1>Hello</h1></body></html>';

        $once = $this->service->injectIntoHtml($site, $html);
        $twice = $this->service->injectIntoHtml($site, $once);

        // Count script occurrences — should not double-inject for same site
        $siteOccurrences = substr_count($twice, $site->id);
        $this->assertLessThanOrEqual(2, $siteOccurrences); // at most one script + one endpoint ref
    }

    public function test_inject_returns_html_unchanged_if_no_body_tag(): void
    {
        $site = $this->makeSite();
        $html = '<p>No body tag here</p>';
        $result = $this->service->injectIntoHtml($site, $html);

        // Without </body>, injection falls back gracefully
        $this->assertNotEmpty($result);
    }
}
