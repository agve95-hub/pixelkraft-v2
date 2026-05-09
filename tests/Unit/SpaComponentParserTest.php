<?php

namespace Tests\Unit;

use App\Models\Site;
use App\Models\User;
use App\Services\PagePreviewService;
use App\Services\Parsers\SpaComponentParser;
use App\Services\Parsers\StaticHtmlParser;
use App\Services\SiteRuntimeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SpaComponentParserTest extends TestCase
{
    use RefreshDatabase;

    private SpaComponentParser $parser;

    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $previews = $this->mock(PagePreviewService::class);
        $previews->shouldReceive('findBuiltHtmlPath')->andReturn(null)->byDefault();

        $runtime = $this->mock(SiteRuntimeService::class);
        $runtime->shouldReceive('usesRuntimeServer')->andReturn(false)->byDefault();

        $this->parser = new SpaComponentParser(
            new StaticHtmlParser,
            $previews,
            $runtime,
        );

        $this->tempDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ui-spa-'.uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeTempDir($this->tempDir);
        parent::tearDown();
    }

    private function removeTempDir(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }
        foreach (scandir($path) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $full = $path.DIRECTORY_SEPARATOR.$item;
            is_dir($full) ? $this->removeTempDir($full) : unlink($full);
        }
        rmdir($path);
    }

    private function writeTempFile(string $relativePath, string $content): string
    {
        $fullPath = $this->tempDir.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $dir = dirname($fullPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($fullPath, $content);

        return $relativePath;
    }

    private function makeSite(string $type = 'react'): Site
    {
        $user = User::create([
            'name' => 'U',
            'email' => 'spa-'.uniqid().'@x.com',
            'password' => Hash::make('secret'),
            'role' => 'admin',
        ]);

        return Site::create([
            'user_id' => $user->id,
            'name' => 'S',
            'slug' => 'spa-'.uniqid(),
            'repo_url' => 'https://github.com/example/spa',
            'branch' => 'main',
            'project_type' => $type,
        ]);
    }

    // ── name ─────────────────────────────────────

    public function test_name_returns_spa_component(): void
    {
        $this->assertSame('spa_component', $this->parser->name());
    }

    // ── parsePage — file not found ───────────────

    public function test_parse_page_returns_null_when_file_missing(): void
    {
        $site = $this->makeSite();
        $result = $this->parser->parsePage($this->tempDir, 'nonexistent.tsx', $site);

        $this->assertNull($result);
    }

    // ── JSX / TSX parsing ────────────────────────

    public function test_parse_tsx_extracts_h1_as_title(): void
    {
        $filePath = $this->writeTempFile('pages/Home.tsx', <<<'TSX'
        export default function Home() {
          return (
            <main>
              <h1>Welcome to our site</h1>
              <p>This is some content here for visitors</p>
            </main>
          );
        }
        TSX);

        $site = $this->makeSite('react');
        $page = $this->parser->parsePage($this->tempDir, $filePath, $site);

        $this->assertNotNull($page);
        $this->assertSame('Welcome to our site', $page->title);
    }

    public function test_parse_tsx_extracts_text_nodes_as_regions(): void
    {
        $filePath = $this->writeTempFile('pages/About.tsx', <<<'TSX'
        export default function About() {
          return (
            <div>
              <p>Learn more about our company and services we provide</p>
            </div>
          );
        }
        TSX);

        $site = $this->makeSite('react');
        $page = $this->parser->parsePage($this->tempDir, $filePath, $site);

        $this->assertNotNull($page);
        $this->assertNotEmpty($page->regions);
    }

    public function test_parse_tsx_sets_correct_url_path_for_pages_directory(): void
    {
        $filePath = $this->writeTempFile('src/pages/about.tsx', <<<'TSX'
        export default function About() {
          return <div><p>About page content is shown here</p></div>;
        }
        TSX);

        $site = $this->makeSite('react');
        $page = $this->parser->parsePage($this->tempDir, $filePath, $site);

        $this->assertNotNull($page);
        $this->assertSame('/about', $page->urlPath);
    }

    // ── Vue SFC parsing ──────────────────────────

    public function test_parse_vue_extracts_text_from_template_block(): void
    {
        $filePath = $this->writeTempFile('pages/Home.vue', <<<'VUE'
        <template>
          <main>
            <h1>Vue Home Page Title Here</h1>
            <p>Some descriptive content that is long enough to count</p>
          </main>
        </template>
        <script>
        export default { name: 'Home' }
        </script>
        VUE);

        $site = $this->makeSite('vue');
        $page = $this->parser->parsePage($this->tempDir, $filePath, $site);

        $this->assertNotNull($page);
        $this->assertSame('Vue Home Page Title Here', $page->title);
    }

    public function test_parse_vue_skips_double_brace_interpolation(): void
    {
        $filePath = $this->writeTempFile('pages/Dynamic.vue', <<<'VUE'
        <template>
          <div>
            <h1>{{ dynamicTitle }}</h1>
            <p>Static content that should be detected fine</p>
          </div>
        </template>
        VUE);

        $site = $this->makeSite('vue');
        $page = $this->parser->parsePage($this->tempDir, $filePath, $site);

        $this->assertNotNull($page);

        $regionContents = array_column($page->regions, 'content');
        foreach ($regionContents as $content) {
            $this->assertStringNotContainsString('{{', $content);
        }
    }

    // ── Svelte parsing ───────────────────────────

    public function test_parse_svelte_strips_script_and_style_blocks(): void
    {
        $filePath = $this->writeTempFile('src/routes/+page.svelte', <<<'SVELTE'
        <script>
          let count = 0;
          const secretVar = "should not appear";
        </script>

        <style>
          h1 { color: red; }
        </style>

        <main>
          <h1>Svelte Page Title Shows Here</h1>
          <p>Readable static content visible to users on this page</p>
        </main>
        SVELTE);

        $site = $this->makeSite('svelte');
        $page = $this->parser->parsePage($this->tempDir, $filePath, $site);

        $this->assertNotNull($page);

        $regionContents = array_column($page->regions, 'content');
        foreach ($regionContents as $content) {
            $this->assertStringNotContainsString('secretVar', $content);
        }
    }

    public function test_parse_svelte_extracts_title_from_svelte_head(): void
    {
        $filePath = $this->writeTempFile('routes/index.svelte', <<<'SVELTE'
        <svelte:head>
          <title>My Svelte App</title>
        </svelte:head>
        <main>
          <p>Welcome to the page content area</p>
        </main>
        SVELTE);

        $site = $this->makeSite('svelte');
        $page = $this->parser->parsePage($this->tempDir, $filePath, $site);

        $this->assertNotNull($page);
        $this->assertSame('My Svelte App', $page->title);
    }

    // ── Astro parsing ────────────────────────────

    public function test_parse_astro_extracts_title_from_frontmatter(): void
    {
        $filePath = $this->writeTempFile('src/pages/index.astro', <<<'ASTRO'
        ---
        title: "Astro Landing Page"
        description: "A great Astro site"
        ---
        <html>
          <body>
            <h1>Welcome</h1>
            <p>Content goes here for the visitors to read</p>
          </body>
        </html>
        ASTRO);

        $site = $this->makeSite('astro');
        $page = $this->parser->parsePage($this->tempDir, $filePath, $site);

        $this->assertNotNull($page);
        $this->assertSame('Astro Landing Page', $page->title);
        $this->assertSame('A great Astro site', $page->metaDescription);
    }

    // ── URL path mapping ─────────────────────────

    public function test_nextjs_app_router_page_tsx_maps_to_route(): void
    {
        $filePath = $this->writeTempFile('app/dashboard/page.tsx', <<<'TSX'
        export default function Dashboard() {
          return <main><h1>Dashboard heading text here</h1></main>;
        }
        TSX);

        $site = $this->makeSite('nextjs');
        $page = $this->parser->parsePage($this->tempDir, $filePath, $site);

        $this->assertNotNull($page);
        $this->assertSame('/dashboard', $page->urlPath);
    }

    public function test_index_component_maps_to_root(): void
    {
        $filePath = $this->writeTempFile('src/pages/index.tsx', <<<'TSX'
        export default function Index() {
          return <main><h1>Home page content is here for users</h1></main>;
        }
        TSX);

        $site = $this->makeSite('react');
        $page = $this->parser->parsePage($this->tempDir, $filePath, $site);

        $this->assertNotNull($page);
        $this->assertSame('/', $page->urlPath);
    }
}
