<?php

namespace Tests\Unit;

use App\Models\Site;
use App\Models\User;
use App\Services\Parsers\ParsedPage;
use App\Services\Parsers\StaticHtmlParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StaticHtmlParserTest extends TestCase
{
    use RefreshDatabase;

    private StaticHtmlParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new StaticHtmlParser;
    }

    private function makeSite(): Site
    {
        $user = User::create([
            'name' => 'U',
            'email' => 'shp-'.uniqid().'@x.com',
            'password' => Hash::make('secret'),
            'role' => 'admin',
        ]);

        return Site::create([
            'user_id' => $user->id,
            'name' => 'S',
            'slug' => 'shp-'.uniqid(),
            'repo_url' => 'https://github.com/example/shp',
            'branch' => 'main',
            'project_type' => 'static_html',
        ]);
    }

    private function parse(string $html, string $filePath = 'index.html'): ?ParsedPage
    {
        return $this->parser->parseHtmlDocument($html, $filePath, $this->makeSite());
    }

    // ── null on empty ────────────────────────────

    public function test_returns_null_for_empty_html(): void
    {
        $this->assertNull($this->parse(''));
        $this->assertNull($this->parse('   '));
    }

    // ── metadata extraction ──────────────────────

    public function test_extracts_title(): void
    {
        $page = $this->parse('<html><head><title>My Page</title></head><body></body></html>');
        $this->assertSame('My Page', $page->title);
    }

    public function test_returns_null_title_when_absent(): void
    {
        $page = $this->parse('<html><head></head><body></body></html>');
        $this->assertNull($page->title);
    }

    public function test_extracts_meta_description(): void
    {
        $page = $this->parse('<html><head><meta name="description" content="Great page"></head><body></body></html>');
        $this->assertSame('Great page', $page->metaDescription);
    }

    public function test_extracts_meta_keywords(): void
    {
        $page = $this->parse('<html><head><meta name="keywords" content="php, laravel"></head><body></body></html>');
        $this->assertSame('php, laravel', $page->metaKeywords);
    }

    public function test_extracts_og_title(): void
    {
        $page = $this->parse('<html><head><meta property="og:title" content="OG Title"></head><body></body></html>');
        $this->assertSame('OG Title', $page->ogTitle);
    }

    public function test_extracts_og_description(): void
    {
        $page = $this->parse('<html><head><meta property="og:description" content="OG Desc"></head><body></body></html>');
        $this->assertSame('OG Desc', $page->ogDescription);
    }

    public function test_extracts_og_image(): void
    {
        $page = $this->parse('<html><head><meta property="og:image" content="https://example.com/img.jpg"></head><body></body></html>');
        $this->assertSame('https://example.com/img.jpg', $page->ogImage);
    }

    public function test_extracts_canonical_url(): void
    {
        $page = $this->parse('<html><head><link rel="canonical" href="https://example.com/page"></head><body></body></html>');
        $this->assertSame('https://example.com/page', $page->canonicalUrl);
    }

    public function test_extracts_schema_json(): void
    {
        $html = '<html><head><script type="application/ld+json">{"@type":"WebPage","name":"Test"}</script></head><body></body></html>';
        $page = $this->parse($html);

        $this->assertNotNull($page->schemaJson);
        $this->assertSame('WebPage', $page->schemaJson[0]['@type']);
    }

    public function test_returns_null_schema_when_absent(): void
    {
        $page = $this->parse('<html><head></head><body></body></html>');
        $this->assertNull($page->schemaJson);
    }

    public function test_ignores_invalid_schema_json(): void
    {
        $html = '<html><head><script type="application/ld+json">not-json</script></head><body></body></html>';
        $page = $this->parse($html);
        $this->assertNull($page->schemaJson);
    }

    // ── URL path conversion ───────────────────────

    public function test_index_html_maps_to_root_path(): void
    {
        $page = $this->parse('<html><head><title>Home</title></head><body></body></html>', 'index.html');
        $this->assertSame('/', $page->urlPath);
    }

    public function test_nested_index_html_maps_to_directory_path(): void
    {
        $page = $this->parse('<html><head></head><body></body></html>', 'about/index.html');
        $this->assertSame('/about', $page->urlPath);
    }

    public function test_non_index_html_maps_to_page_path(): void
    {
        $page = $this->parse('<html><head></head><body></body></html>', 'contact.html');
        $this->assertSame('/contact', $page->urlPath);
    }

    // ── content hash ────────────────────────────

    public function test_content_hash_is_md5_of_html(): void
    {
        $html = '<html><head><title>T</title></head><body></body></html>';
        $page = $this->parse($html);
        $this->assertSame(md5($html), $page->contentHash);
    }

    // ── file path ───────────────────────────────

    public function test_file_path_is_preserved(): void
    {
        $page = $this->parse('<html><head></head><body></body></html>', 'products/widget.html');
        $this->assertSame('products/widget.html', $page->filePath);
    }

    // ── cms marker region detection ──────────────

    public function test_detects_cms_editable_marker_region(): void
    {
        $html = <<<HTML
        <html><body>
        <!-- cms:editable id="hero-title" type="text" -->
        <h1>Welcome</h1>
        <!-- /cms:editable -->
        </body></html>
        HTML;

        $page = $this->parse($html);
        $markerRegions = array_filter($page->regions, fn ($r) => ($r['marker_id'] ?? null) === 'hero-title');

        $this->assertNotEmpty($markerRegions);
        $region = array_values($markerRegions)[0];
        $this->assertSame('text', $region['type']);
        $this->assertSame(1.0, $region['confidence']);
        $this->assertFalse($region['is_static']);
    }

    public function test_detects_pk_editable_marker_region(): void
    {
        $html = <<<HTML
        <html><body>
        <!-- pk:editable:start:my-heading type="text" -->
        <h2>Section Title</h2>
        <!-- pk:editable:end:my-heading -->
        </body></html>
        HTML;

        $page = $this->parse($html);
        $markerRegions = array_filter($page->regions, fn ($r) => ($r['marker_id'] ?? null) === 'my-heading');

        $this->assertNotEmpty($markerRegions);
    }

    // ── auto region detection ────────────────────

    public function test_auto_detects_h1_as_region(): void
    {
        $html = '<html><body><main><h1>Big Headline Worth Editing</h1></main></body></html>';
        $page = $this->parse($html);

        $h1Regions = array_filter($page->regions, fn ($r) => str_contains($r['selector'] ?? '', 'h1'));

        $this->assertNotEmpty($h1Regions);
    }

    public function test_does_not_create_regions_for_empty_elements(): void
    {
        $html = '<html><body><p></p></body></html>';
        $page = $this->parse($html);

        // Empty <p> should be skipped during auto-detection
        $emptyPRegions = array_filter(
            $page->regions,
            fn ($r) => ($r['content'] ?? null) === ''
        );

        $this->assertEmpty($emptyPRegions);
    }
}
