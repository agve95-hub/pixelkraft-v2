<?php

namespace Tests\Feature;

use App\Enums\DeployStatus;
use App\Livewire\Editor\VisualEditor;
use App\Livewire\Files\FileManager;
use App\Models\Page;
use App\Models\Site;
use App\Models\User;
use App\Services\GitSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class EditorAndFileRollbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_code_editor_restores_source_file_when_git_push_fails(): void
    {
        $root = storage_path('framework/testing/editor-rollback-'.uniqid());
        File::ensureDirectoryExists($root);
        File::put($root.'/index.html', '<h1>Before</h1>');

        try {
            [$user, $site] = $this->makeSite($root);
            $page = Page::create([
                'site_id' => $site->id,
                'file_path' => 'index.html',
                'url_path' => '/',
                'title' => 'Home',
            ]);

            $this->mockGitPushFailure();

            Livewire::actingAs($user)
                ->test(VisualEditor::class, ['siteId' => $site->id, 'pageId' => $page->id])
                ->set('mode', 'code')
                ->set('codeContent', '<h1>After</h1>')
                ->set('deployAfterSave', false)
                ->call('save');

            $this->assertSame('<h1>Before</h1>', File::get($root.'/index.html'));
        } finally {
            File::deleteDirectory($root);
        }
    }

    public function test_file_manager_restores_file_when_git_push_fails(): void
    {
        $root = storage_path('framework/testing/file-rollback-'.uniqid());
        File::ensureDirectoryExists($root);
        File::put($root.'/style.css', 'body { color: black; }');

        try {
            [$user, $site] = $this->makeSite($root, 'file-rollback@example.com');

            $this->mockGitPushFailure();

            Livewire::actingAs($user)
                ->test(FileManager::class, ['siteId' => $site->id])
                ->call('viewFile', 'style.css')
                ->set('fileContent', 'body { color: orange; }')
                ->call('saveFile');

            $this->assertSame('body { color: black; }', File::get($root.'/style.css'));
        } finally {
            File::deleteDirectory($root);
        }
    }

    /**
     * @return array{User, Site}
     */
    private function makeSite(string $repoPath, string $email = 'editor-rollback@example.com'): array
    {
        $user = User::create([
            'name' => 'Rollback User',
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => 'editor',
        ]);

        $site = Site::create([
            'user_id' => $user->id,
            'name' => 'Rollback Site',
            'slug' => 'rollback-site-'.uniqid(),
            'repo_url' => 'https://github.com/example/rollback.git',
            'branch' => 'main',
            'project_type' => 'static_html',
            'deploy_status' => DeployStatus::Live,
            'repo_path' => $repoPath,
            'github_token' => 'ghp_fake_token_for_test',
        ]);

        return [$user, $site];
    }

    private function mockGitPushFailure(): void
    {
        $this->mock(GitSyncService::class, function ($mock): void {
            $mock->shouldReceive('isCloned')->andReturn(false);
            $mock->shouldReceive('commitAndPush')->andThrow(new \RuntimeException('remote rejected'));
        });
    }
}
