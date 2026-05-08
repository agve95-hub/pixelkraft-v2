<?php

namespace Tests\Feature;

use App\Enums\DeployStatus;
use App\Livewire\Seo\MetaEditor;
use App\Livewire\Seo\RobotsTxtEditor;
use App\Livewire\Seo\SchemaEditor;
use App\Models\Page;
use App\Models\Site;
use App\Models\User;
use App\Services\GitSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class SeoEditorRollbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_meta_editor_restores_source_file_when_git_push_fails(): void
    {
        $root = storage_path('framework/testing/meta-rollback-'.uniqid());
        File::ensureDirectoryExists($root);
        $original = '<html><head><title>Before</title></head><body></body></html>';
        File::put($root.'/index.html', $original);

        try {
            [$user, $site] = $this->makeSite($root);
            $page = $this->makePage($site);

            $this->mockGitPushFailure();

            Livewire::actingAs($user)
                ->test(MetaEditor::class, ['pageId' => $page->id])
                ->set('title', 'After')
                ->call('save');

            $this->assertSame($original, File::get($root.'/index.html'));
            $this->assertSame('Before', $page->fresh()->title);
        } finally {
            File::deleteDirectory($root);
        }
    }

    public function test_schema_editor_restores_source_file_when_git_push_fails(): void
    {
        $root = storage_path('framework/testing/schema-rollback-'.uniqid());
        File::ensureDirectoryExists($root);
        $original = '<html><head><title>Before</title></head><body></body></html>';
        File::put($root.'/index.html', $original);

        try {
            [$user, $site] = $this->makeSite($root, 'schema-rollback@example.com');
            $page = $this->makePage($site);

            $this->mockGitPushFailure();

            Livewire::actingAs($user)
                ->test(SchemaEditor::class, ['pageId' => $page->id])
                ->set('schemaJson', '{"@context":"https://schema.org","@type":"WebPage"}')
                ->call('save');

            $this->assertSame($original, File::get($root.'/index.html'));
            $this->assertNull($page->fresh()->schema_json);
        } finally {
            File::deleteDirectory($root);
        }
    }

    public function test_robots_editor_restores_repo_and_deploy_files_when_git_push_fails(): void
    {
        $root = storage_path('framework/testing/robots-rollback-'.uniqid());
        $deployRoot = storage_path('framework/testing/robots-deploy-'.uniqid());
        File::ensureDirectoryExists($root.'/public');
        File::ensureDirectoryExists($deployRoot);
        File::put($root.'/public/robots.txt', "User-agent: *\nAllow: /");
        File::put($deployRoot.'/robots.txt', "User-agent: *\nAllow: /");

        try {
            [$user, $site] = $this->makeSite($root, 'robots-rollback@example.com', [
                'build_output_dir' => 'public',
                'deploy_path' => $deployRoot,
            ]);

            $this->mockGitPushFailure();

            Livewire::actingAs($user)
                ->test(RobotsTxtEditor::class, ['siteId' => $site->id])
                ->set('content', "User-agent: *\nDisallow: /private")
                ->call('save');

            $this->assertSame("User-agent: *\nAllow: /", File::get($root.'/public/robots.txt'));
            $this->assertSame("User-agent: *\nAllow: /", File::get($deployRoot.'/robots.txt'));
        } finally {
            File::deleteDirectory($root);
            File::deleteDirectory($deployRoot);
        }
    }

    public function test_robots_editor_rejects_unsafe_build_output_directory(): void
    {
        $root = storage_path('framework/testing/robots-safe-'.uniqid());
        $outside = storage_path('framework/testing/robots-outside-'.uniqid());
        File::ensureDirectoryExists($root);
        File::ensureDirectoryExists($outside);

        try {
            [$user, $site] = $this->makeSite($root, 'robots-safe@example.com', [
                'build_output_dir' => '../'.basename($outside),
            ]);

            $this->mock(GitSyncService::class, function ($mock): void {
                $mock->shouldReceive('isCloned')->andReturn(true);
                $mock->shouldNotReceive('commitAndPush');
            });

            Livewire::actingAs($user)
                ->test(RobotsTxtEditor::class, ['siteId' => $site->id])
                ->set('content', "User-agent: *\nDisallow: /")
                ->call('save');

            $this->assertFileDoesNotExist($outside.'/robots.txt');
        } finally {
            File::deleteDirectory($root);
            File::deleteDirectory($outside);
        }
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{User, Site}
     */
    private function makeSite(string $repoPath, string $email = 'seo-rollback@example.com', array $overrides = []): array
    {
        $user = User::create([
            'name' => 'SEO User',
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => 'editor',
        ]);

        $site = Site::create(array_merge([
            'user_id' => $user->id,
            'name' => 'SEO Site',
            'slug' => 'seo-site-'.uniqid(),
            'repo_url' => 'https://github.com/example/seo.git',
            'branch' => 'main',
            'project_type' => 'static_html',
            'deploy_status' => DeployStatus::Live,
            'repo_path' => $repoPath,
            'github_token' => 'ghp_fake_token_for_test',
        ], $overrides));

        return [$user, $site];
    }

    private function makePage(Site $site): Page
    {
        return Page::create([
            'site_id' => $site->id,
            'file_path' => 'index.html',
            'url_path' => '/',
            'title' => 'Before',
        ]);
    }

    private function mockGitPushFailure(): void
    {
        $this->mock(GitSyncService::class, function ($mock): void {
            $mock->shouldReceive('isCloned')->andReturn(true);
            $mock->shouldReceive('commitAndPush')->andThrow(new \RuntimeException('remote rejected'));
        });
    }
}
