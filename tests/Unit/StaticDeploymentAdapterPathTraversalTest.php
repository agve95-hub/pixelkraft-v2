<?php

namespace Tests\Unit;

use App\Models\Site;
use App\Services\Deployment\StaticDeploymentAdapter;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Verify the defence-in-depth realpath guard in StaticDeploymentAdapter.
 *
 * The guard fires when build_output_dir resolves outside the repo directory
 * via symlink or dotdot traversal.  Input validation in SiteSettings /
 * SiteManager already blocks dotdot paths, so this test exercises the case
 * where the directory exists on disk and would otherwise be served.
 */
class StaticDeploymentAdapterPathTraversalTest extends TestCase
{
    private string $repoDir;

    private string $outsideDir;

    protected function setUp(): void
    {
        parent::setUp();

        $base = sys_get_temp_dir().'/tool_static_adapter_test_'.uniqid('', true);
        $this->repoDir = $base.'/repo';
        $this->outsideDir = $base.'/outside';

        mkdir($this->repoDir, 0755, true);
        mkdir($this->outsideDir, 0755, true);

        // Put a file in each so realpath() can resolve them
        file_put_contents($this->repoDir.'/index.html', '<h1>hello</h1>');
        file_put_contents($this->outsideDir.'/secret.txt', 'SENSITIVE');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(dirname($this->repoDir));
        parent::tearDown();
    }

    /** Build a minimal, unsaved Site model pointing at the temp repo. */
    private function makeSite(string $buildOutputDir): Site
    {
        $site = new Site;
        $site->repo_path = $this->repoDir;
        $site->project_type = 'static_html';
        $site->build_output_dir = $buildOutputDir;
        $site->slug = 'test-site';

        return $site;
    }

    public function test_artifact_directory_throws_when_output_dir_escapes_repo_via_dotdot(): void
    {
        // '../outside' resolves to $outsideDir, which is a sibling of $repoDir
        $site = $this->makeSite('../outside');

        $adapter = new StaticDeploymentAdapter;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/outside.*repository/i');

        $adapter->artifactDirectory($site);
    }

    public function test_artifact_directory_accepts_legitimate_subdir(): void
    {
        // Create a real subdirectory inside the repo
        $distDir = $this->repoDir.'/dist';
        mkdir($distDir, 0755, true);
        file_put_contents($distDir.'/index.html', '<h1>built</h1>');

        $site = $this->makeSite('dist');

        $adapter = new StaticDeploymentAdapter;

        $result = $adapter->artifactDirectory($site);

        $this->assertNotNull($result);
        $this->assertStringContainsString('dist', $result);
    }

    public function test_artifact_directory_returns_repo_root_when_no_build_output_dir(): void
    {
        $site = $this->makeSite('');
        $site->build_output_dir = null;

        $adapter = new StaticDeploymentAdapter;

        // Falls back to the repo root (no /public subdir exists in the temp repo)
        $result = $adapter->artifactDirectory($site);

        $this->assertNotNull($result);
        $this->assertStringContainsString($this->repoDir, (string) $result);
    }
}
