<?php

namespace Tests\Unit;

use App\Models\Site;
use App\Models\User;
use App\Services\Parsers\SsgOutputParser;
use App\Services\Parsers\StaticHtmlParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SsgOutputParserTest extends TestCase
{
    use RefreshDatabase;

    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'pk-ssg-'.uniqid();
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

    private function writeFile(string $relPath, string $content): void
    {
        $full = $this->tempDir.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relPath);
        if (! is_dir(dirname($full))) {
            mkdir(dirname($full), 0777, true);
        }
        file_put_contents($full, $content);
    }

    private function makeUser(): User
    {
        return User::create([
            'name' => 'U',
            'email' => 'ssgp-'.uniqid().'@x.com',
            'password' => Hash::make('secret'),
            'role' => 'admin',
        ]);
    }

    private function makeSite(User $user, array $attrs = []): Site
    {
        return Site::create(array_merge([
            'user_id' => $user->id,
            'name' => 'S',
            'slug' => 'ssgp-'.uniqid(),
            'repo_url' => 'https://github.com/example/ssgp',
            'branch' => 'main',
            'project_type' => 'hugo',
        ], $attrs));
    }

    private function makeParser(): SsgOutputParser
    {
        return new SsgOutputParser(new StaticHtmlParser);
    }

    // ── name ─────────────────────────────────────

    public function test_name_returns_ssg_output(): void
    {
        $this->assertSame('ssg_output', $this->makeParser()->name());
    }

    // ── parsePage ────────────────────────────────

    public function test_returns_null_for_nonexistent_file(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $result = $this->makeParser()->parsePage($this->tempDir, 'public/missing.html', $site);

        $this->assertNull($result);
    }

    public function test_delegates_to_static_parser_for_html_file(): void
    {
        $this->writeFile('public/index.html', '<html><head><title>Hugo Home</title></head><body></body></html>');
        $user = $this->makeUser();
        $site = $this->makeSite($user, ['build_output_dir' => 'public']);

        $result = $this->makeParser()->parsePage($this->tempDir, 'public/index.html', $site);

        $this->assertNotNull($result);
        $this->assertSame('Hugo Home', $result->title);
    }

    public function test_enriches_region_source_location_with_source_file_info(): void
    {
        $html = <<<'HTML'
        <html><head><title>T</title></head>
        <body><main><h1>Built Page Heading Here</h1></main></body>
        </html>
        HTML;

        $this->writeFile('public/index.html', $html);
        // Create matching source file so findSourceFile finds it
        $this->writeFile('content/index.md', '# Home');

        $user = $this->makeUser();
        $site = $this->makeSite($user, ['build_output_dir' => 'public', 'project_type' => 'hugo']);

        $result = $this->makeParser()->parsePage($this->tempDir, 'public/index.html', $site);

        $this->assertNotNull($result);
        // Regions should have source_location enriched
        foreach ($result->regions as $region) {
            $this->assertArrayHasKey('source_location', $region);
        }
    }

    // ── discoverPages ────────────────────────────

    public function test_discovers_html_files_in_output_dir(): void
    {
        $this->writeFile('public/index.html', '<html></html>');
        $this->writeFile('public/about.html', '<html></html>');
        $this->writeFile('public/blog/post.html', '<html></html>');

        $user = $this->makeUser();
        $site = $this->makeSite($user, ['build_output_dir' => 'public']);

        $pages = $this->makeParser()->discoverPages($this->tempDir, $site);

        $this->assertCount(3, $pages);
        // Normalize separators for cross-platform compatibility
        $normalized = array_map(fn ($p) => str_replace('\\', '/', $p), $pages);
        $this->assertTrue(collect($normalized)->contains(fn ($p) => str_ends_with($p, 'public/index.html')));
    }

    public function test_returns_empty_when_output_dir_not_found(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user, ['build_output_dir' => 'nonexistent_output']);

        $pages = $this->makeParser()->discoverPages($this->tempDir, $site);

        $this->assertSame([], $pages);
    }

    public function test_guesses_output_dir_for_hugo_when_not_set(): void
    {
        // Hugo defaults to 'public'
        $this->writeFile('public/index.html', '<html></html>');

        $user = $this->makeUser();
        $site = $this->makeSite($user, ['project_type' => 'hugo', 'build_output_dir' => null]);

        $pages = $this->makeParser()->discoverPages($this->tempDir, $site);

        $this->assertNotEmpty($pages);
        // Normalize separators for cross-platform compatibility
        $normalized = array_map(fn ($p) => str_replace('\\', '/', $p), $pages);
        $this->assertTrue(collect($normalized)->contains(fn ($p) => str_ends_with($p, 'index.html')));
    }

    public function test_guesses_output_dir_for_eleventy_when_not_set(): void
    {
        $this->writeFile('_site/index.html', '<html></html>');

        $user = $this->makeUser();
        $site = $this->makeSite($user, ['project_type' => 'eleventy', 'build_output_dir' => null]);

        $pages = $this->makeParser()->discoverPages($this->tempDir, $site);

        $this->assertNotEmpty($pages);
    }
}
