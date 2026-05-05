<?php

namespace Tests\Unit;

use App\Models\Site;
use App\Models\User;
use App\Services\ProjectDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProjectDetectorTest extends TestCase
{
    use RefreshDatabase;

    private ProjectDetector $detector;

    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->detector = new ProjectDetector;
        $this->tempDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'pk-detect-'.uniqid();
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

    private function makeSite(string $repoPath): Site
    {
        $user = User::create([
            'name' => 'U',
            'email' => 'pd-'.uniqid().'@x.com',
            'password' => Hash::make('secret'),
            'role' => 'admin',
        ]);

        $site = Site::create([
            'user_id' => $user->id,
            'name' => 'Detect',
            'slug' => 'pd-'.uniqid(),
            'repo_url' => 'https://github.com/example/detect',
            'branch' => 'main',
            'project_type' => 'custom',
        ]);

        // Override repo_path via the model attribute so ProjectDetector reads our temp dir.
        $site->repo_path = $repoPath;

        return $site;
    }

    private function writeFile(string $path, string $content = ''): void
    {
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($path, $content);
    }

    private function writePackageJson(array $deps = [], array $devDeps = [], array $scripts = []): void
    {
        $pkg = [
            'name' => 'test',
            'dependencies' => $deps,
            'devDependencies' => $devDeps,
            'scripts' => $scripts,
        ];
        $this->writeFile($this->tempDir.'/package.json', json_encode($pkg));
    }

    // ── no repo ──────────────────────────────────

    public function test_returns_custom_when_repo_path_not_a_directory(): void
    {
        $site = $this->makeSite('/nonexistent/path/'.uniqid());
        $result = $this->detector->detect($site);

        $this->assertSame('custom', $result['type']);
        $this->assertSame(0.0, $result['confidence']);
    }

    // ── static HTML ──────────────────────────────

    public function test_detects_static_html_with_index_html(): void
    {
        $this->writeFile($this->tempDir.'/index.html', '<h1>Hello</h1>');
        $site = $this->makeSite($this->tempDir);

        $result = $this->detector->detect($site);

        $this->assertSame('static_html', $result['type']);
        $this->assertGreaterThanOrEqual(0.9, $result['confidence']);
    }

    public function test_detects_static_html_without_index_has_lower_confidence(): void
    {
        $this->writeFile($this->tempDir.'/about.html', '<h1>About</h1>');
        $site = $this->makeSite($this->tempDir);

        $result = $this->detector->detect($site);

        $this->assertSame('static_html', $result['type']);
        $this->assertLessThan(0.9, $result['confidence']);
    }

    // ── PHP site ─────────────────────────────────

    public function test_detects_php_site_with_index_php(): void
    {
        $this->writeFile($this->tempDir.'/index.php', '<?php echo "hello";');
        $site = $this->makeSite($this->tempDir);

        $result = $this->detector->detect($site);

        $this->assertSame('php_site', $result['type']);
    }

    // ── Astro ────────────────────────────────────

    public function test_detects_astro_from_package_json(): void
    {
        $this->writePackageJson(deps: ['astro' => '^4.0.0']);
        $site = $this->makeSite($this->tempDir);

        $result = $this->detector->detect($site);

        $this->assertSame('astro', $result['type']);
        $this->assertSame('dist', $result['build_output_dir']);
        $this->assertGreaterThanOrEqual(0.95, $result['confidence']);
    }

    public function test_detects_astro_from_config_file(): void
    {
        $this->writeFile($this->tempDir.'/astro.config.mjs', 'export default {}');
        $site = $this->makeSite($this->tempDir);

        $result = $this->detector->detect($site);

        $this->assertSame('astro', $result['type']);
    }

    // ── Next.js ──────────────────────────────────

    public function test_detects_nextjs_from_package_json(): void
    {
        $this->writePackageJson(deps: ['next' => '^14.0.0', 'react' => '^18.0.0']);
        $site = $this->makeSite($this->tempDir);

        $result = $this->detector->detect($site);

        $this->assertSame('nextjs', $result['type']);
    }

    public function test_detects_nextjs_static_export_from_config(): void
    {
        $this->writePackageJson(deps: ['next' => '^14.0.0']);
        $this->writeFile($this->tempDir.'/next.config.js', "module.exports = { output: 'export' }");
        $site = $this->makeSite($this->tempDir);

        $result = $this->detector->detect($site);

        $this->assertSame('nextjs', $result['type']);
        $this->assertSame('out', $result['build_output_dir']);
    }

    // ── Hugo ─────────────────────────────────────

    public function test_detects_hugo_from_config_file(): void
    {
        $this->writeFile($this->tempDir.'/hugo.toml', "baseURL = 'https://example.com'");
        $site = $this->makeSite($this->tempDir);

        $result = $this->detector->detect($site);

        $this->assertSame('hugo', $result['type']);
        $this->assertSame('public', $result['build_output_dir']);
        $this->assertSame('hugo --minify', $result['build_command']);
    }

    // ── Eleventy ─────────────────────────────────

    public function test_detects_eleventy_from_config_file(): void
    {
        $this->writeFile($this->tempDir.'/.eleventy.js', 'module.exports = {}');
        $site = $this->makeSite($this->tempDir);

        $result = $this->detector->detect($site);

        $this->assertSame('eleventy', $result['type']);
        $this->assertSame('_site', $result['build_output_dir']);
    }

    // ── React ────────────────────────────────────

    public function test_detects_react_with_vite_uses_dist_dir(): void
    {
        $this->writePackageJson(deps: ['react' => '^18.0.0', 'vite' => '^5.0.0']);
        $site = $this->makeSite($this->tempDir);

        $result = $this->detector->detect($site);

        $this->assertSame('react', $result['type']);
        $this->assertSame('dist', $result['build_output_dir']);
    }

    public function test_detects_react_without_vite_uses_build_dir(): void
    {
        $this->writePackageJson(deps: ['react' => '^18.0.0', 'react-scripts' => '^5.0.0']);
        $site = $this->makeSite($this->tempDir);

        $result = $this->detector->detect($site);

        $this->assertSame('react', $result['type']);
        $this->assertSame('build', $result['build_output_dir']);
    }

    // ── package manager detection ─────────────────

    public function test_detects_pnpm_lock_and_uses_pnpm_command(): void
    {
        $this->writePackageJson(deps: ['astro' => '^4.0.0'], scripts: ['build' => 'astro build']);
        $this->writeFile($this->tempDir.'/pnpm-lock.yaml', '');
        $site = $this->makeSite($this->tempDir);

        $result = $this->detector->detect($site);

        $this->assertStringContainsString('pnpm', $result['build_command']);
    }

    public function test_detects_bun_lock_and_uses_bun_command(): void
    {
        $this->writePackageJson(deps: ['astro' => '^4.0.0'], scripts: ['build' => 'astro build']);
        $this->writeFile($this->tempDir.'/bun.lockb', '');
        $site = $this->makeSite($this->tempDir);

        $result = $this->detector->detect($site);

        $this->assertStringContainsString('bun', $result['build_command']);
    }

    // ── fallback ─────────────────────────────────

    public function test_returns_custom_with_low_confidence_for_empty_directory(): void
    {
        $site = $this->makeSite($this->tempDir);

        $result = $this->detector->detect($site);

        $this->assertSame('custom', $result['type']);
        $this->assertLessThan(0.5, $result['confidence']);
    }
}
