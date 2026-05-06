<?php

namespace Tests\Unit;

use App\Models\Site;
use App\Models\User;
use App\Services\Deployment\StaticDeploymentAdapter;
use App\Services\SiteRuntimeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StaticDeploymentAdapterTest extends TestCase
{
    use RefreshDatabase;

    private StaticDeploymentAdapter $adapter;

    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adapter = new StaticDeploymentAdapter;
        $this->tempDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'pk-sda-'.uniqid();
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

    private function makeUser(): User
    {
        return User::create([
            'name' => 'U',
            'email' => 'sda-'.uniqid().'@x.com',
            'password' => Hash::make('secret'),
            'role' => 'admin',
        ]);
    }

    private function makeSite(User $user, array $attrs = []): Site
    {
        return Site::create(array_merge([
            'user_id' => $user->id,
            'name' => 'S',
            'slug' => 'sda-'.uniqid(),
            'repo_url' => 'https://github.com/example/sda',
            'branch' => 'main',
            'project_type' => 'static_html',
        ], $attrs));
    }

    // ── mode / label ─────────────────────────────

    public function test_mode_returns_static(): void
    {
        $this->assertSame(SiteRuntimeService::MODE_STATIC, $this->adapter->mode());
    }

    public function test_activation_step_label(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $this->assertSame('Deploying static files...', $this->adapter->activationStepLabel($site));
    }

    // ── supportsAggressiveOptimization ────────────

    public function test_static_html_supports_aggressive_optimization(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user, ['project_type' => 'static_html']);

        $this->assertTrue($this->adapter->supportsAggressiveOptimization($site));
    }

    public function test_hugo_supports_aggressive_optimization(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user, ['project_type' => 'hugo']);

        $this->assertTrue($this->adapter->supportsAggressiveOptimization($site));
    }

    public function test_eleventy_supports_aggressive_optimization(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user, ['project_type' => 'eleventy']);

        $this->assertTrue($this->adapter->supportsAggressiveOptimization($site));
    }

    public function test_nextjs_does_not_support_aggressive_optimization(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user, ['project_type' => 'nextjs']);

        $this->assertFalse($this->adapter->supportsAggressiveOptimization($site));
    }

    public function test_react_does_not_support_aggressive_optimization(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user, ['project_type' => 'react']);

        $this->assertFalse($this->adapter->supportsAggressiveOptimization($site));
    }

    // ── artifactDirectory ─────────────────────────

    public function test_artifact_directory_returns_null_when_repo_path_missing(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user, ['repo_path' => '/nonexistent/'.uniqid()]);

        $result = $this->adapter->artifactDirectory($site);

        $this->assertNull($result);
    }

    public function test_artifact_directory_returns_repo_path_when_no_output_dir(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user, [
            'repo_path' => $this->tempDir,
            'build_output_dir' => null,
        ]);

        $result = $this->adapter->artifactDirectory($site);

        $this->assertSame($this->tempDir, $result);
    }

    public function test_artifact_directory_returns_configured_output_dir(): void
    {
        mkdir($this->tempDir.DIRECTORY_SEPARATOR.'dist', 0777, true);

        $user = $this->makeUser();
        $site = $this->makeSite($user, [
            'repo_path' => $this->tempDir,
            'build_output_dir' => 'dist',
        ]);

        $result = $this->adapter->artifactDirectory($site);

        // Normalize separators for Windows (adapter uses '/' internally)
        $this->assertStringEndsWith('dist', str_replace('\\', '/', (string) $result));
    }

    public function test_artifact_directory_falls_back_to_public_subdir(): void
    {
        mkdir($this->tempDir.DIRECTORY_SEPARATOR.'public', 0777, true);

        $user = $this->makeUser();
        $site = $this->makeSite($user, [
            'repo_path' => $this->tempDir,
            'build_output_dir' => null,
        ]);

        $result = $this->adapter->artifactDirectory($site);

        // Normalize separators for Windows
        $this->assertStringEndsWith('public', str_replace('\\', '/', (string) $result));
    }

    public function test_artifact_directory_rejects_traversal_path(): void
    {
        // Create a sibling dir that ../escape would resolve to
        $siblingDir = dirname($this->tempDir).DIRECTORY_SEPARATOR.'sibling-'.uniqid();
        mkdir($siblingDir, 0777, true);

        $user = $this->makeUser();
        $site = $this->makeSite($user, [
            'repo_path' => $this->tempDir,
            'build_output_dir' => '../'.basename($siblingDir),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('outside of repository');

        try {
            $this->adapter->artifactDirectory($site);
        } finally {
            $this->removeDir($siblingDir);
        }
    }

    // ── activate — no deploy path ─────────────────

    public function test_activate_throws_when_no_deploy_path(): void
    {
        // Site::creating auto-assigns deploy_path; force-clear it after create
        $user = $this->makeUser();
        $site = $this->makeSite($user, ['repo_path' => $this->tempDir, 'build_output_dir' => null]);
        \Illuminate\Support\Facades\DB::table('sites')->where('id', $site->id)->update(['deploy_path' => null]);
        $site->refresh();

        $log = new \App\Models\DeployLog(['site_id' => $site->id, 'status' => 'deploying']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No deploy path configured');

        $this->adapter->activate($site, $log);
    }
}
