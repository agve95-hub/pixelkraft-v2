<?php

namespace Tests\Unit;

use App\Models\Site;
use App\Models\User;
use App\Services\Import\ImportException;
use App\Services\Import\ZipImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class ZipImportServiceTest extends TestCase
{
    use RefreshDatabase;

    private ZipImportService $service;

    private string $repoDir;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->service = new ZipImportService;
        $this->repoDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ui-zip-'.uniqid();
        mkdir($this->repoDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->repoDir);
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

    private function makeSite(): Site
    {
        $user = User::create([
            'name' => 'U',
            'email' => 'zip-'.uniqid().'@x.com',
            'password' => Hash::make('secret'),
            'role' => 'admin',
        ]);

        $site = Site::create([
            'user_id' => $user->id,
            'name' => 'S',
            'slug' => 'zip-'.uniqid(),
            'repo_url' => 'https://github.com/example/zip',
            'branch' => 'main',
            'project_type' => 'static_html',
            'source_type' => 'git',
            'repo_path' => $this->repoDir,
        ]);

        return $site;
    }

    /**
     * Build a real ZIP file in the faked local storage disk and return the disk path.
     */
    private function buildZip(array $entries, string $diskPath = 'imports/test.zip'): string
    {
        $realPath = Storage::disk('local')->path($diskPath);
        if (! is_dir(dirname($realPath))) {
            mkdir(dirname($realPath), 0777, true);
        }

        $zip = new ZipArchive;
        $zip->open($realPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($entries as $name => $content) {
            $zip->addFromString($name, $content);
        }

        $zip->close();

        return $diskPath;
    }

    // ── non-existent ZIP ─────────────────────────

    public function test_throws_import_exception_when_zip_not_found(): void
    {
        $this->expectException(ImportException::class);
        $this->expectExceptionMessage('ZIP file not found');

        $this->service->import($this->makeSite(), 'missing/path.zip');
    }

    // ── path traversal rejection ─────────────────

    public function test_rejects_zip_with_dotdot_path_traversal(): void
    {
        $diskPath = $this->buildZip(['../etc/passwd' => 'malicious']);

        $this->expectException(ImportException::class);
        $this->expectExceptionMessage('path-traversal');

        $this->service->import($this->makeSite(), $diskPath);
    }

    public function test_rejects_zip_with_absolute_path_entry(): void
    {
        // ZipArchive silently strips the leading slash — so '/etc/passwd' becomes
        // 'etc/passwd' inside the archive. Test that embedded traversal is caught.
        $diskPath = $this->buildZip(['safe/../../escape.txt' => 'bad']);

        $this->expectException(ImportException::class);

        $this->service->import($this->makeSite(), $diskPath);
    }

    // ── allowed / disallowed extensions ─────────

    public function test_extracts_allowed_html_extension(): void
    {
        // Files must be in a wrapper dir — the extraction guard rejects root-level entries
        $diskPath = $this->buildZip(['dist/index.html' => '<html><body>ok</body></html>']);
        $site = $this->makeSite();

        $result = $this->service->import($site, $diskPath);

        $this->assertGreaterThanOrEqual(1, $result->fileCount);
        $this->assertFileExists($this->repoDir.DIRECTORY_SEPARATOR.'index.html');
    }

    public function test_skips_disallowed_php_extension(): void
    {
        $diskPath = $this->buildZip([
            'dist/index.html' => '<html>ok</html>',
            'dist/shell.php' => '<?php system($_GET["cmd"]); ?>',
        ]);
        $site = $this->makeSite();

        $this->service->import($site, $diskPath);

        $this->assertFileDoesNotExist($this->repoDir.DIRECTORY_SEPARATOR.'shell.php');
        $this->assertFileExists($this->repoDir.DIRECTORY_SEPARATOR.'index.html');
    }

    public function test_skips_ds_store_files(): void
    {
        $diskPath = $this->buildZip([
            'dist/index.html' => '<html>ok</html>',
            'dist/.DS_Store' => 'mac metadata',
        ]);
        $site = $this->makeSite();

        $this->service->import($site, $diskPath);

        $this->assertFileDoesNotExist($this->repoDir.DIRECTORY_SEPARATOR.'.DS_Store');
    }

    // ── project type detection ───────────────────

    public function test_detects_static_html_from_html_files(): void
    {
        $diskPath = $this->buildZip(['dist/index.html' => '<html>ok</html>']);
        $site = $this->makeSite();

        $result = $this->service->import($site, $diskPath);

        $this->assertSame('static_html', $result->projectType);
    }

    public function test_detects_nextjs_from_config_file(): void
    {
        $diskPath = $this->buildZip([
            'build/package.json' => '{"name":"app"}',
            'build/next.config.js' => 'module.exports = {}',
            'build/index.html' => '<html>ok</html>',
        ]);
        $site = $this->makeSite();

        $result = $this->service->import($site, $diskPath);

        $this->assertSame('nextjs', $result->projectType);
    }

    public function test_detects_php_site_from_composer_json(): void
    {
        // .php extension is blocked by the allowlist, so php_site detection
        // must come from composer.json (allowed as JSON) rather than index.php.
        $diskPath = $this->buildZip([
            'app/composer.json' => '{"name":"my/site","require":{"php":"^8.3"}}',
            'app/index.html' => '<html>ok</html>',
        ]);
        $site = $this->makeSite();

        $result = $this->service->import($site, $diskPath);

        $this->assertSame('php_site', $result->projectType);
    }

    // ── flattening single top-level dir ──────────

    public function test_flattens_single_top_level_directory(): void
    {
        $diskPath = $this->buildZip([
            'dist/index.html' => '<html>ok</html>',
            'dist/style.css' => 'body {}',
        ]);
        $site = $this->makeSite();

        $result = $this->service->import($site, $diskPath);

        // Files should be at the root after flattening
        $this->assertFileExists($this->repoDir.DIRECTORY_SEPARATOR.'index.html');
        $this->assertSame(2, $result->fileCount);
    }

    public function test_does_not_flatten_multiple_top_level_dirs(): void
    {
        $diskPath = $this->buildZip([
            'dist/index.html' => '<html>ok</html>',
            'src/app.js' => 'const x = 1;',
        ]);
        $site = $this->makeSite();

        $result = $this->service->import($site, $diskPath);

        // Not flattened — both directories remain
        $this->assertDirectoryExists($this->repoDir.DIRECTORY_SEPARATOR.'dist');
        $this->assertDirectoryExists($this->repoDir.DIRECTORY_SEPARATOR.'src');
    }

    // ── import result ────────────────────────────

    public function test_returns_import_result_with_file_count(): void
    {
        $diskPath = $this->buildZip([
            'dist/index.html' => '<html>Home</html>',
            'dist/about.html' => '<html>About</html>',
            'dist/style.css' => 'body {}',
        ]);
        $site = $this->makeSite();

        $result = $this->service->import($site, $diskPath);

        $this->assertSame(3, $result->fileCount);
    }

    public function test_deletes_zip_from_storage_after_import(): void
    {
        $diskPath = $this->buildZip(['dist/index.html' => '<html>ok</html>']);
        $site = $this->makeSite();

        $this->service->import($site, $diskPath);

        Storage::disk('local')->assertMissing($diskPath);
    }

    public function test_updates_site_project_type_after_import(): void
    {
        $diskPath = $this->buildZip(['dist/index.html' => '<html>ok</html>']);
        $site = $this->makeSite();

        $this->service->import($site, $diskPath);

        $site->refresh();
        $this->assertSame('static_html', $site->project_type);
    }
}
