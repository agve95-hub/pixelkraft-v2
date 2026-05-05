<?php

namespace Tests\Unit;

use App\Models\Site;
use App\Models\User;
use App\Services\Parsers\RenderedPhpParser;
use App\Services\Parsers\StaticHtmlParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RenderedPhpParserTest extends TestCase
{
    use RefreshDatabase;

    private RenderedPhpParser $parser;

    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new RenderedPhpParser(new StaticHtmlParser);
        $this->tempDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'pk-php-'.uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
        parent::tearDown();
    }

    private function removeDir(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }
        foreach (scandir($path) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $full = $path.DIRECTORY_SEPARATOR.$item;
            is_dir($full) ? $this->removeDir($full) : unlink($full);
        }
        rmdir($path);
    }

    private function writeFile(string $relativePath, string $content): string
    {
        $full = $this->tempDir.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        if (! is_dir(dirname($full))) {
            mkdir(dirname($full), 0777, true);
        }
        file_put_contents($full, $content);

        return $relativePath;
    }

    private function makeSite(): Site
    {
        $user = User::create([
            'name' => 'U',
            'email' => 'rpp-'.uniqid().'@x.com',
            'password' => Hash::make('secret'),
            'role' => 'admin',
        ]);

        return Site::create([
            'user_id' => $user->id,
            'name' => 'S',
            'slug' => 'rpp-'.uniqid(),
            'repo_url' => 'https://github.com/example/rpp',
            'branch' => 'main',
            'project_type' => 'php_site',
        ]);
    }

    // ── name ─────────────────────────────────────

    public function test_name_returns_rendered_php(): void
    {
        $this->assertSame('rendered_php', $this->parser->name());
    }

    // ── parsePage — file not found ───────────────

    public function test_returns_null_for_nonexistent_file(): void
    {
        $site = $this->makeSite();
        $result = $this->parser->parsePage($this->tempDir, 'missing.php', $site);

        $this->assertNull($result);
    }

    public function test_returns_null_for_empty_file(): void
    {
        $this->writeFile('empty.php', '   ');
        $site = $this->makeSite();

        $result = $this->parser->parsePage($this->tempDir, 'empty.php', $site);

        $this->assertNull($result);
    }

    // ── PHP tag stripping ─────────────────────────

    public function test_strips_php_tags_from_content(): void
    {
        $this->writeFile('page.php', <<<'PHP'
        <?php $title = "Hello"; ?>
        <html><head><title>My Page</title></head>
        <body><h1>Hello World</h1></body>
        </html>
        PHP);

        $site = $this->makeSite();
        $result = $this->parser->parsePage($this->tempDir, 'page.php', $site);

        $this->assertNotNull($result);
        $this->assertSame('My Page', $result->title);
    }

    // ── Blade directive stripping ─────────────────

    public function test_strips_blade_directives(): void
    {
        $this->writeFile('resources/views/home.blade.php', <<<'BLADE'
        @extends('layouts.app')
        @section('content')
        <html><head><title>Blade Page</title></head>
        <body><h1>Welcome Home</h1></body>
        </html>
        @endsection
        BLADE);

        $site = $this->makeSite();
        $result = $this->parser->parsePage($this->tempDir, 'resources/views/home.blade.php', $site);

        $this->assertNotNull($result);
        $this->assertSame('Blade Page', $result->title);
    }

    public function test_replaces_blade_echo_with_content_placeholder(): void
    {
        $this->writeFile('resources/views/dynamic.blade.php', <<<'BLADE'
        <html><head><title>{{ $pageTitle }}</title></head>
        <body><h1>Static Heading</h1></body>
        </html>
        BLADE);

        $site = $this->makeSite();
        $result = $this->parser->parsePage($this->tempDir, 'resources/views/dynamic.blade.php', $site);

        $this->assertNotNull($result);
        // {{ $pageTitle }} becomes ' content ' — trimmed to 'content' by title extraction
        $this->assertSame('content', $result->title);
    }

    // ── URL path mapping ─────────────────────────

    public function test_blade_view_maps_to_url_path(): void
    {
        $this->writeFile('resources/views/about.blade.php', <<<'BLADE'
        <html><head><title>About</title></head><body></body></html>
        BLADE);

        $site = $this->makeSite();
        $result = $this->parser->parsePage($this->tempDir, 'resources/views/about.blade.php', $site);

        $this->assertNotNull($result);
        $this->assertSame('/about', $result->urlPath);
    }

    public function test_index_blade_maps_to_root_path(): void
    {
        $this->writeFile('resources/views/index.blade.php', <<<'BLADE'
        <html><head><title>Home</title></head><body></body></html>
        BLADE);

        $site = $this->makeSite();
        $result = $this->parser->parsePage($this->tempDir, 'resources/views/index.blade.php', $site);

        $this->assertNotNull($result);
        $this->assertSame('/', $result->urlPath);
    }

    public function test_public_php_file_maps_to_url_path(): void
    {
        $this->writeFile('public/contact.php', <<<'PHP'
        <html><head><title>Contact</title></head><body></body></html>
        PHP);

        $site = $this->makeSite();
        $result = $this->parser->parsePage($this->tempDir, 'public/contact.php', $site);

        $this->assertNotNull($result);
        $this->assertSame('/contact', $result->urlPath);
    }

    // ── discoverPages ────────────────────────────

    public function test_discovers_php_and_blade_files(): void
    {
        $this->writeFile('resources/views/home.blade.php', '<html></html>');
        $this->writeFile('resources/views/about.blade.php', '<html></html>');
        $this->writeFile('public/index.php', '<?php echo "ok"; ?>');

        $site = $this->makeSite();
        $pages = $this->parser->discoverPages($this->tempDir, $site);

        $this->assertNotEmpty($pages);
        $this->assertTrue(collect($pages)->contains(fn ($p) => str_ends_with($p, 'home.blade.php')));
        $this->assertTrue(collect($pages)->contains(fn ($p) => str_ends_with($p, 'index.php')));
    }

    public function test_skips_vendor_directory(): void
    {
        $this->writeFile('vendor/autoload.php', '<?php // vendor');
        $this->writeFile('public/index.php', '<?php echo "ok";');

        $site = $this->makeSite();
        $pages = $this->parser->discoverPages($this->tempDir, $site);

        $vendorFiles = array_filter($pages, fn ($p) => str_contains(str_replace('\\', '/', $p), 'vendor/'));
        $this->assertEmpty($vendorFiles);
    }

    public function test_returns_empty_array_when_no_directories_exist(): void
    {
        $site = $this->makeSite();
        $pages = $this->parser->discoverPages($this->tempDir, $site);

        $this->assertSame([], $pages);
    }
}
