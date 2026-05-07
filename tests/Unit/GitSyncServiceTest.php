<?php

namespace Tests\Unit;

use App\Models\GitOperation;
use App\Models\Site;
use App\Models\User;
use App\Services\GitSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class GitSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_commit_and_push_keeps_local_commit_when_site_has_no_write_token(): void
    {
        $root = storage_path('framework/testing/git-sync-'.uniqid());
        $repoPath = $root.'/repo';

        try {
            File::ensureDirectoryExists($repoPath);
            $this->git(['init'], $repoPath);
            $this->git(['checkout', '-b', 'main'], $repoPath);

            file_put_contents($repoPath.'/index.html', 'before');
            $this->git(['add', 'index.html'], $repoPath);
            $this->git(['-c', 'user.name=Test', '-c', 'user.email=test@example.test', 'commit', '-m', 'Initial'], $repoPath);

            $missingRemote = str_replace('\\', '/', $root).'/missing.git';
            $this->git(['remote', 'add', 'origin', $missingRemote], $repoPath);

            $user = User::create([
                'name' => 'Editor',
                'email' => 'editor-'.uniqid().'@example.test',
                'password' => Hash::make('secret'),
                'role' => 'editor',
            ]);

            $site = Site::create([
                'user_id' => $user->id,
                'name' => 'Local Save Site',
                'slug' => 'local-save-'.uniqid(),
                'repo_url' => 'https://github.com/example/local-save.git',
                'branch' => 'main',
                'project_type' => 'static_html',
                'repo_path' => $repoPath,
                'deploy_path' => $root.'/deploy',
                'github_token' => null,
            ]);

            file_put_contents($repoPath.'/index.html', 'after');

            $sha = app(GitSyncService::class)->commitAndPush($site, ['index.html'], 'Update home');

            $this->assertSame($sha, trim($this->git(['rev-parse', 'HEAD'], $repoPath)));
            $this->assertSame('', trim($this->git(['status', '--porcelain'], $repoPath)));

            $operation = GitOperation::where('site_id', $site->id)->latest('started_at')->firstOrFail();
            $this->assertSame('success', $operation->status);
            $this->assertSame($sha, $operation->commit_sha);
            $this->assertStringContainsString('Committed locally', (string) $operation->output_log);
            $this->assertStringContainsString('no write token', (string) $operation->output_log);
        } finally {
            File::deleteDirectory($root);
        }
    }

    /**
     * @param  list<string>  $arguments
     */
    private function git(array $arguments, string $cwd): string
    {
        $process = new Process(array_merge(['git'], $arguments), $cwd);
        $process->setTimeout(30);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->fail(trim($process->getErrorOutput() ?: $process->getOutput()));
        }

        return $process->getOutput();
    }
}
