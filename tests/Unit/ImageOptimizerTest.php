<?php

namespace Tests\Unit;

use App\Services\ImageOptimizer;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class ImageOptimizerTest extends TestCase
{
    private ImageOptimizer $optimizer;

    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->optimizer = new ImageOptimizer;
        $this->tempDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ui-img-'.uniqid();
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

    private function writeFile(string $name, string $content = 'data'): string
    {
        $path = $this->tempDir.DIRECTORY_SEPARATOR.$name;
        file_put_contents($path, $content);

        return $path;
    }

    // ── optimizeDirectory ────────────────────────

    public function test_optimize_directory_returns_zero_for_nonexistent_directory(): void
    {
        $count = $this->optimizer->optimizeDirectory('/nonexistent/path/'.uniqid());

        $this->assertSame(0, $count);
    }

    public function test_optimize_directory_returns_zero_when_no_images(): void
    {
        $this->writeFile('readme.txt', 'hello');
        $this->writeFile('style.css', 'body {}');

        Process::fake(['*' => Process::result('', '', 0)]);

        $count = $this->optimizer->optimizeDirectory($this->tempDir);

        $this->assertSame(0, $count);
    }

    public function test_optimize_directory_skips_node_modules(): void
    {
        $nodeDir = $this->tempDir.DIRECTORY_SEPARATOR.'node_modules';
        mkdir($nodeDir, 0777, true);
        file_put_contents($nodeDir.DIRECTORY_SEPARATOR.'icon.svg', '<svg/>');

        Process::fake(['*' => Process::result('/usr/bin/svgo', '', 0)]);

        // No files should be optimized from node_modules
        $this->optimizer->optimizeDirectory($this->tempDir);

        Process::assertNothingRan();
    }

    public function test_optimize_directory_attempts_optimization_for_svg_files(): void
    {
        $this->writeFile('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><rect/></svg>');

        Process::fake([
            'which svgo' => Process::result('/usr/bin/svgo', '', 0),
            '*svgo*' => Process::result('', '', 0),
        ]);

        $count = $this->optimizer->optimizeDirectory($this->tempDir);

        $this->assertSame(1, $count);
    }

    public function test_optimize_directory_returns_zero_when_tools_missing(): void
    {
        $this->writeFile('photo.jpg', 'fake-jpeg');

        // All `which` commands fail → tools not installed
        Process::fake(['*' => Process::result('', 'not found', 1)]);

        $count = $this->optimizer->optimizeDirectory($this->tempDir);

        $this->assertSame(0, $count);
    }

    // ── optimizeFile ─────────────────────────────

    public function test_optimize_file_returns_false_for_unknown_extension(): void
    {
        $path = $this->writeFile('document.pdf', 'pdf-content');

        $result = $this->optimizer->optimizeFile($path);

        $this->assertFalse($result);
    }

    public function test_optimize_file_returns_false_when_jpeg_tool_missing(): void
    {
        $path = $this->writeFile('photo.jpg', 'fake-jpeg');

        Process::fake(['*' => Process::result('', 'not found', 1)]);

        $result = $this->optimizer->optimizeFile($path);

        $this->assertFalse($result);
    }

    public function test_optimize_file_returns_false_when_png_tool_missing(): void
    {
        $path = $this->writeFile('icon.png', 'fake-png');

        Process::fake(['*' => Process::result('', 'not found', 1)]);

        $result = $this->optimizer->optimizeFile($path);

        $this->assertFalse($result);
    }

    public function test_optimize_file_returns_true_when_jpeg_tool_succeeds(): void
    {
        $path = $this->writeFile('photo.jpg', 'fake-jpeg');

        Process::fake([
            'which jpegoptim' => Process::result('/usr/bin/jpegoptim', '', 0),
            '*jpegoptim*' => Process::result('', '', 0),
        ]);

        $result = $this->optimizer->optimizeFile($path);

        $this->assertTrue($result);
    }

    public function test_optimize_file_returns_true_when_svg_tool_succeeds(): void
    {
        $path = $this->writeFile('logo.svg', '<svg/>');

        Process::fake([
            'which svgo' => Process::result('/usr/bin/svgo', '', 0),
            '*svgo*' => Process::result('', '', 0),
        ]);

        $result = $this->optimizer->optimizeFile($path);

        $this->assertTrue($result);
    }

    // ── generateWebp ─────────────────────────────

    public function test_generate_webp_returns_false_when_cwebp_missing(): void
    {
        $path = $this->writeFile('photo.jpg', 'fake-jpeg');

        Process::fake(['*' => Process::result('', 'not found', 1)]);

        $result = $this->optimizer->generateWebp($path);

        $this->assertFalse($result);
    }

    public function test_generate_webp_returns_true_when_cwebp_succeeds(): void
    {
        $path = $this->writeFile('photo.jpg', 'fake-jpeg');

        Process::fake([
            'which cwebp' => Process::result('/usr/bin/cwebp', '', 0),
            '*cwebp*' => Process::result('', '', 0),
        ]);

        $result = $this->optimizer->generateWebp($path);

        $this->assertTrue($result);
    }
}
