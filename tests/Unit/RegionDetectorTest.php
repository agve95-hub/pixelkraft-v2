<?php

namespace Tests\Unit;

use App\Models\EditableRegion;
use App\Models\Page;
use App\Models\Site;
use App\Models\User;
use App\Services\RegionDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegionDetectorTest extends TestCase
{
    use RefreshDatabase;

    private RegionDetector $detector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->detector = new RegionDetector;
    }

    private function makeUser(): User
    {
        return User::create([
            'name' => 'U',
            'email' => 'rd-'.uniqid().'@x.com',
            'password' => Hash::make('secret'),
            'role' => 'editor',
        ]);
    }

    private function makeSite(User $user): Site
    {
        return Site::create([
            'user_id' => $user->id,
            'name' => 'S',
            'slug' => 'rd-'.uniqid(),
            'repo_url' => 'https://github.com/example/rd',
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

    private function makeRegion(Page $page, array $attrs = []): EditableRegion
    {
        return EditableRegion::create(array_merge([
            'page_id' => $page->id,
            'selector' => '#hero',
            'region_type' => 'text',
            'is_static' => false,
            'detection_method' => 'auto',
            'confidence_score' => 0.8,
            'current_content' => 'Hello World',
        ], $attrs));
    }

    // ── confirmAsEditable ────────────────────────

    public function test_confirm_as_editable_sets_is_static_false_and_confidence_1(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);
        $page = $this->makePage($site);
        $region = $this->makeRegion($page, ['is_static' => true, 'confidence_score' => 0.3]);

        $this->detector->confirmAsEditable($region);

        $region->refresh();
        $this->assertFalse($region->is_static);
        $this->assertSame(1.0, $region->confidence_score);
        $this->assertSame('manual', $region->detection_method);
        $this->assertNotNull($region->last_verified_at);
    }

    public function test_confirm_as_editable_stores_marker_id(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);
        $page = $this->makePage($site);
        $region = $this->makeRegion($page);

        $this->detector->confirmAsEditable($region, 'hero-title');

        $region->refresh();
        $this->assertSame('hero-title', $region->marker_id);
        $this->assertSame('marker', $region->detection_method);
        $this->assertSame('marker', $region->source_anchor['verified_via']);
    }

    public function test_confirm_as_editable_without_marker_uses_manual_detection(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);
        $page = $this->makePage($site);
        $region = $this->makeRegion($page);

        $this->detector->confirmAsEditable($region, null);

        $region->refresh();
        $this->assertSame('manual', $region->detection_method);
        $this->assertSame('manual', $region->source_anchor['verified_via']);
    }

    // ── confirmAsStatic ──────────────────────────

    public function test_confirm_as_static_sets_is_static_true(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);
        $page = $this->makePage($site);
        $region = $this->makeRegion($page, ['is_static' => false]);

        $this->detector->confirmAsStatic($region);

        $region->refresh();
        $this->assertTrue($region->is_static);
        $this->assertSame(1.0, $region->confidence_score);
        $this->assertTrue($region->source_anchor['locked']);
        $this->assertNotNull($region->last_verified_at);
    }

    // ── generateMarkerId ─────────────────────────

    public function test_generate_marker_id_includes_region_type_as_prefix(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);
        $page = $this->makePage($site);
        $region = $this->makeRegion($page, ['region_type' => 'heading', 'current_content' => 'Welcome to our site']);

        $markerId = $this->detector->generateMarkerId($region);

        $this->assertStringStartsWith('heading-', $markerId);
    }

    public function test_generate_marker_id_slugifies_content(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);
        $page = $this->makePage($site);
        $region = $this->makeRegion($page, ['current_content' => 'Hello World']);

        $markerId = $this->detector->generateMarkerId($region);

        $this->assertStringContainsString('hello-world', $markerId);
    }

    public function test_generate_marker_id_falls_back_to_region_id_when_empty_content(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);
        $page = $this->makePage($site);
        $region = $this->makeRegion($page, ['current_content' => '']);

        $markerId = $this->detector->generateMarkerId($region);

        $this->assertNotEmpty($markerId);
    }

    // ── injectMarkers ────────────────────────────

    public function test_inject_markers_wraps_id_element(): void
    {
        $html = '<div><div id="hero"><h1>Title</h1></div></div>';
        $regions = [
            ['marker_id' => 'hero-block', 'selector' => '#hero', 'region_type' => 'section'],
        ];

        $result = $this->detector->injectMarkers($html, $regions);

        $this->assertStringContainsString('<!-- pk:editable:start:hero-block', $result);
        $this->assertStringContainsString('<!-- pk:editable:end:hero-block -->', $result);
    }

    public function test_inject_markers_skips_region_without_marker_id(): void
    {
        $html = '<div id="hero"><h1>Title</h1></div>';
        $regions = [
            ['marker_id' => '', 'selector' => '#hero', 'region_type' => 'text'],
        ];

        $result = $this->detector->injectMarkers($html, $regions);

        $this->assertStringNotContainsString('pk:editable', $result);
    }

    public function test_inject_markers_wraps_class_element(): void
    {
        $html = '<section class="hero-section"><h1>Hello</h1></section>';
        $regions = [
            ['marker_id' => 'hero-title', 'selector' => 'section.hero-section', 'region_type' => 'text'],
        ];

        $result = $this->detector->injectMarkers($html, $regions);

        $this->assertStringContainsString('pk:editable:start:hero-title', $result);
    }

    public function test_inject_markers_returns_html_unchanged_when_selector_not_found(): void
    {
        $html = '<div><p>Hello</p></div>';
        $regions = [
            ['marker_id' => 'notfound', 'selector' => '#nonexistent', 'region_type' => 'text'],
        ];

        $result = $this->detector->injectMarkers($html, $regions);

        // No markers added but HTML returned intact
        $this->assertStringContainsString('<p>Hello</p>', $result);
    }
}
