<?php

namespace Tests\Unit;

use App\Models\Site;
use App\Services\PagePreviewService;
use App\Services\SiteRuntimeService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class PagePreviewServiceTest extends TestCase
{
    public function test_it_uses_project_specific_static_output_directories(): void
    {
        $service = app(PagePreviewService::class);

        $astroSite = new Site([
            'project_type' => 'astro',
            'deployment_mode' => SiteRuntimeService::MODE_STATIC,
        ]);
        $phpSite = new Site([
            'project_type' => 'php_site',
            'deployment_mode' => SiteRuntimeService::MODE_STATIC,
        ]);

        $this->assertContains('dist', $service->staticOutputDirs($astroSite));
        $this->assertContains('public', $service->staticOutputDirs($phpSite));
    }

    public function test_it_finds_built_html_inside_framework_output_directories(): void
    {
        $repoPath = storage_path('framework/testing/disks/' . Str::uuid());
        File::ensureDirectoryExists($repoPath . '/dist/about', 0755, true);
        File::put($repoPath . '/dist/about/index.html', '<html><body>About</body></html>');

        $site = new Site([
            'project_type' => 'astro',
            'deployment_mode' => SiteRuntimeService::MODE_STATIC,
            'repo_path' => $repoPath,
        ]);

        $path = app(PagePreviewService::class)->findBuiltHtmlPath($site, '/about');

        $this->assertSame('dist/about/index.html', $path);
    }

    public function test_configured_output_dir_takes_priority_over_defaults(): void
    {
        $repoPath = storage_path('framework/testing/disks/' . Str::uuid());
        File::ensureDirectoryExists($repoPath . '/custom-output/docs', 0755, true);
        File::put($repoPath . '/custom-output/docs/index.html', '<html><body>Docs</body></html>');

        $site = new Site([
            'project_type' => 'react',
            'deployment_mode' => SiteRuntimeService::MODE_STATIC,
            'repo_path' => $repoPath,
            'build_output_dir' => 'custom-output',
        ]);

        $path = app(PagePreviewService::class)->findBuiltHtmlPath($site, '/docs');

        $this->assertSame('custom-output/docs/index.html', $path);
        $this->assertSame('custom-output', app(PagePreviewService::class)->contextForRepoRelativePath($site, $path)['root_prefix']);
    }
}
