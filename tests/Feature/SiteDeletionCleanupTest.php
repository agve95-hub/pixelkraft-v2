<?php

namespace Tests\Feature;

use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class SiteDeletionCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_site_removes_site_filesystem_artifacts(): void
    {
        $slug = 'cleanup-'.Str::lower(Str::random(8));
        $sandboxRoot = storage_path("framework/testing/site-deletion-{$slug}");

        $reposRoot = "{$sandboxRoot}/repos";
        $deployRoot = "{$sandboxRoot}/deploy";
        $runtimeStorageRoot = "{$sandboxRoot}/runtime-sites";
        $runtimePidRoot = "{$sandboxRoot}/runtime-pids";
        $runtimeLogRoot = "{$sandboxRoot}/runtime-logs";
        $nginxAvailableRoot = "{$sandboxRoot}/nginx/sites-available";
        $nginxEnabledRoot = "{$sandboxRoot}/nginx/sites-enabled";

        config()->set('platform.runtime.storage_path', $runtimeStorageRoot);
        config()->set('platform.runtime.pid_path', $runtimePidRoot);
        config()->set('platform.runtime.log_path', $runtimeLogRoot);
        config()->set('platform.nginx_sites_path', $nginxAvailableRoot);

        $repoPath = "{$reposRoot}/{$slug}";
        $deployPath = "{$deployRoot}/{$slug}";
        $runtimeRootPath = "{$runtimeStorageRoot}/{$slug}";
        $runtimePidPath = "{$runtimePidRoot}/{$slug}.pid";
        $runtimeLogPath = "{$runtimeLogRoot}/{$slug}.log";
        $nginxConfPath = "{$nginxAvailableRoot}/{$slug}.conf";
        $nginxEnabledPath = "{$nginxEnabledRoot}/{$slug}.conf";

        try {
            File::ensureDirectoryExists($repoPath);
            File::put("{$repoPath}/index.html", '<h1>repo</h1>');

            File::ensureDirectoryExists($deployPath);
            File::put("{$deployPath}/index.html", '<h1>deploy</h1>');

            File::ensureDirectoryExists($runtimeRootPath);
            File::put("{$runtimeRootPath}/server.js", 'console.log("runtime");');

            File::ensureDirectoryExists(dirname($runtimePidPath));
            File::put($runtimePidPath, '12345');

            File::ensureDirectoryExists(dirname($runtimeLogPath));
            File::put($runtimeLogPath, 'runtime log');

            File::ensureDirectoryExists(dirname($nginxConfPath));
            File::put($nginxConfPath, 'server {}');

            File::ensureDirectoryExists(dirname($nginxEnabledPath));
            File::put($nginxEnabledPath, 'enabled');

            $site = Site::create([
                'name' => 'Cleanup Test',
                'slug' => $slug,
                'repo_url' => 'https://github.com/acme/demo.git',
                'branch' => 'main',
                'repo_path' => $repoPath,
                'deploy_path' => $deployPath,
                'nginx_conf_path' => $nginxConfPath,
            ]);

            $site->delete();

            $this->assertDatabaseMissing('sites', ['id' => $site->id]);
            $this->assertFalse(File::exists($repoPath));
            $this->assertFalse(File::exists($deployPath));
            $this->assertFalse(File::exists($runtimeRootPath));
            $this->assertFalse(File::exists($runtimePidPath));
            $this->assertFalse(File::exists($runtimeLogPath));
            $this->assertFalse(File::exists($nginxConfPath));
            $this->assertFalse(File::exists($nginxEnabledPath));

            // Parent roots should remain so cleanup is site-scoped only.
            $this->assertTrue(File::isDirectory($reposRoot));
            $this->assertTrue(File::isDirectory($deployRoot));
            $this->assertTrue(File::isDirectory($runtimeStorageRoot));
        } finally {
            File::deleteDirectory($sandboxRoot);
        }
    }
}
