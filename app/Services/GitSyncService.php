<?php

namespace App\Services;

use App\Models\EditSession;
use App\Models\GitOperation;
use App\Models\Site;
use CzProject\GitPhp\Git;
use CzProject\GitPhp\GitException;
use CzProject\GitPhp\GitRepository;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class GitSyncService
{
    private Git $git;

    public function __construct(
        private SiteLockService $locks,
    ) {
        $this->git = new Git();
    }

    public function cloneRepo(Site $site, ?EditSession $session = null): GitRepository
    {
        return $this->locks->block($site, 'git', function () use ($site, $session) {
            $repoPath = $site->repo_path;
            $operation = $this->startOperation($site, 'clone', $session);

            if (File::isDirectory($repoPath)) {
                File::deleteDirectory($repoPath);
            }

            File::ensureDirectoryExists(dirname($repoPath), 0755, true);

            try {
                $authUrl = $this->buildAuthUrl($site);
                $repo = $this->git->cloneRepository($authUrl, $repoPath, [
                    '-b' => $site->branch,
                    '--single-branch',
                    '--depth' => 50,
                ]);

                $site->update(['last_synced_at' => now()]);
                $this->finishOperation($operation, 'success', output: "Cloned {$site->repo_url} into {$repoPath}");

                return $repo;
            } catch (\Throwable $e) {
                $this->finishOperation($operation, 'failed', error: $e->getMessage());
                throw $e;
            }
        });
    }

    public function pull(Site $site, ?EditSession $session = null): bool
    {
        return $this->locks->block($site, 'git', function () use ($site, $session) {
            $repo = $this->openRepo($site);
            $beforeSha = $this->getCurrentSha($repo);
            $operation = $this->startOperation($site, 'pull', $session, [
                'branch' => $site->branch,
                'commit_sha' => $beforeSha,
            ]);

            $this->configureAuth($repo, $site);

            try {
                $output = $repo->execute('pull', '--ff-only', 'origin', $site->branch);
                $afterSha = $this->getCurrentSha($repo);

                $site->update(['last_synced_at' => now()]);
                $this->finishOperation(
                    $operation,
                    'success',
                    commitSha: $afterSha,
                    output: $this->stringifyOutput($output)
                );

                return $beforeSha !== $afterSha;
            } catch (GitException $e) {
                if ($this->isConflict($e)) {
                    try {
                        $repo->execute('merge', '--abort');
                    } catch (\Throwable) {
                        // Ignore when no merge is in progress.
                    }

                    $this->finishOperation($operation, 'conflict', error: $e->getMessage());

                    throw new GitConflictException(
                        "Merge conflict detected for site [{$site->slug}]. Remote changes conflict with local edits.",
                        previous: $e
                    );
                }

                $this->finishOperation($operation, 'failed', error: $e->getMessage());
                throw $e;
            }
        });
    }

    public function commitAndPush(
        Site $site,
        array $files,
        string $message,
        ?EditSession $session = null,
    ): string {
        return $this->locks->block($site, 'git', function () use ($site, $files, $message, $session) {
            $repo = $this->openRepo($site);
            $this->configureAuth($repo, $site);

            $operation = $this->startOperation($site, 'commit', $session, [
                'branch' => $site->branch,
                'working_branch' => $session?->working_branch,
                'files' => array_values($files),
            ]);

            try {
                $this->configureCommitIdentity($repo);

                foreach ($files as $file) {
                    $repo->addFile($file);
                }

                if (! $this->hasChanges($repo)) {
                    $sha = $this->getCurrentSha($repo);
                    $this->finishOperation($operation, 'noop', commitSha: $sha, output: 'No changes to commit.');
                    return $sha;
                }

                $repo->commit($message);
                $repo->execute('push', 'origin', $site->branch);

                $sha = $this->getCurrentSha($repo);

                $this->finishOperation($operation, 'success', commitSha: $sha, output: $message);

                Log::info("Pushed commit for site [{$site->slug}]", [
                    'sha' => $sha,
                    'message' => $message,
                    'files' => $files,
                ]);

                return $sha;
            } catch (GitException $e) {
                if ($this->isPushRejected($e)) {
                    try {
                        $sha = $this->pullRebaseAndPush($site, $repo);
                        $this->finishOperation($operation, 'success', commitSha: $sha, output: 'Push rejected, recovered with pull --rebase.');
                        return $sha;
                    } catch (\Throwable $rebaseException) {
                        $this->finishOperation($operation, 'conflict', error: $rebaseException->getMessage());
                        throw $rebaseException;
                    }
                }

                $this->finishOperation($operation, 'failed', error: $e->getMessage());
                throw $e;
            } catch (\Throwable $e) {
                $this->finishOperation($operation, 'failed', error: $e->getMessage());
                throw $e;
            }
        });
    }

    public function commitAllAndPush(Site $site, string $message, ?EditSession $session = null): string
    {
        return $this->locks->block($site, 'git', function () use ($site, $message, $session) {
            $repo = $this->openRepo($site);
            $this->configureAuth($repo, $site);
            $operation = $this->startOperation($site, 'commit', $session, [
                'branch' => $site->branch,
                'working_branch' => $session?->working_branch,
            ]);

            try {
                $this->configureCommitIdentity($repo);
                $repo->addAllChanges();

                if (! $this->hasChanges($repo)) {
                    $sha = $this->getCurrentSha($repo);
                    $this->finishOperation($operation, 'noop', commitSha: $sha, output: 'No changes to commit.');
                    return $sha;
                }

                $repo->commit($message);
                $repo->execute('push', 'origin', $site->branch);

                $sha = $this->getCurrentSha($repo);
                $this->finishOperation($operation, 'success', commitSha: $sha, output: $message);

                return $sha;
            } catch (GitException $e) {
                if ($this->isPushRejected($e)) {
                    try {
                        $sha = $this->pullRebaseAndPush($site, $repo);
                        $this->finishOperation($operation, 'success', commitSha: $sha, output: 'Push rejected, recovered with pull --rebase.');
                        return $sha;
                    } catch (\Throwable $rebaseException) {
                        $this->finishOperation($operation, 'conflict', error: $rebaseException->getMessage());
                        throw $rebaseException;
                    }
                }

                $this->finishOperation($operation, 'failed', error: $e->getMessage());
                throw $e;
            }
        });
    }

    public function getChangedFiles(Site $site, string $fromSha, string $toSha): array
    {
        $repo = $this->openRepo($site);
        $output = $repo->execute('diff', '--name-only', $fromSha, $toSha);

        return array_values(array_filter(array_map('trim', $output)));
    }

    public function currentCommitSha(Site $site): string
    {
        return $this->getCurrentSha($this->openRepo($site));
    }

    public function getCurrentSha(GitRepository $repo): string
    {
        $output = $repo->execute('rev-parse', 'HEAD');

        return trim((string) ($output[0] ?? ''));
    }

    public function getRecentCommits(Site $site, int $limit = 10): array
    {
        $repo = $this->openRepo($site);

        $output = $repo->execute(
            'log',
            "--max-count={$limit}",
            '--format=%H||%s||%ai||%an',
        );

        $commits = [];
        foreach ($output as $line) {
            $parts = explode('||', trim((string) $line));
            if (count($parts) === 4) {
                $commits[] = [
                    'sha' => $parts[0],
                    'message' => $parts[1],
                    'date' => $parts[2],
                    'author' => $parts[3],
                ];
            }
        }

        return $commits;
    }

    public function createTag(Site $site, string $tagName, ?EditSession $session = null): void
    {
        $this->locks->block($site, 'git', function () use ($site, $tagName, $session) {
            $repo = $this->openRepo($site);
            $operation = $this->startOperation($site, 'tag', $session, ['metadata' => ['tag' => $tagName]]);

            try {
                $repo->createTag($tagName);
                $this->finishOperation($operation, 'success', output: "Created tag {$tagName}");
            } catch (\Throwable $e) {
                $this->finishOperation($operation, 'failed', error: $e->getMessage());
                throw $e;
            }
        });
    }

    public function checkout(Site $site, string $ref, ?EditSession $session = null): void
    {
        $this->locks->block($site, 'git', function () use ($site, $ref, $session) {
            $repo = $this->openRepo($site);
            $operation = $this->startOperation($site, 'checkout', $session, ['metadata' => ['ref' => $ref]]);

            try {
                $repo->checkout($ref);
                $this->finishOperation($operation, 'success', output: "Checked out {$ref}");
            } catch (\Throwable $e) {
                $this->finishOperation($operation, 'failed', error: $e->getMessage());
                throw $e;
            }
        });
    }

    public function isCloned(Site $site): bool
    {
        return File::isDirectory($site->repo_path . '/.git');
    }

    public function listFiles(Site $site, ?array $extensions = null): array
    {
        $repoPath = $site->repo_path;

        if (! File::isDirectory($repoPath)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($repoPath, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                continue;
            }

            $relativePath = str_replace($repoPath . '/', '', $file->getPathname());

            if ($this->shouldSkipPath($relativePath)) {
                continue;
            }

            if ($extensions && ! in_array($file->getExtension(), $extensions, true)) {
                continue;
            }

            $files[] = $relativePath;
        }

        sort($files);

        return $files;
    }

    private function openRepo(Site $site): GitRepository
    {
        if (! $this->isCloned($site)) {
            throw new \RuntimeException("Repository not cloned for site [{$site->slug}] at [{$site->repo_path}]");
        }

        return $this->git->open($site->repo_path);
    }

    private function buildAuthUrl(Site $site): string
    {
        if (empty($site->github_token)) {
            return $site->repo_url;
        }

        $parsed = parse_url($site->repo_url);
        $host = $parsed['host'] ?? 'github.com';
        $path = ltrim((string) ($parsed['path'] ?? ''), '/');

        return "https://x-access-token:{$site->github_token}@{$host}/{$path}";
    }

    private function configureAuth(GitRepository $repo, Site $site): void
    {
        if (empty($site->github_token)) {
            return;
        }

        $repo->execute('remote', 'set-url', 'origin', $this->buildAuthUrl($site));
    }

    private function configureCommitIdentity(GitRepository $repo): void
    {
        $repo->execute('config', 'user.name', 'pixelkraft');
        $repo->execute('config', 'user.email', 'pixelkraft@local');
    }

    private function hasChanges(GitRepository $repo): bool
    {
        $output = $repo->execute('status', '--porcelain');

        return ! empty(array_filter($output));
    }

    private function isConflict(GitException $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'conflict')
            || str_contains($message, 'merge')
            || str_contains($message, 'not possible');
    }

    private function isPushRejected(GitException $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'rejected')
            || str_contains($message, 'non-fast-forward')
            || str_contains($message, 'fetch first');
    }

    private function pullRebaseAndPush(Site $site, GitRepository $repo): string
    {
        try {
            $repo->execute('pull', '--rebase', 'origin', $site->branch);
            $repo->execute('push', 'origin', $site->branch);

            return $this->getCurrentSha($repo);
        } catch (GitException $e) {
            try {
                $repo->execute('rebase', '--abort');
            } catch (\Throwable) {
                // Ignore when no rebase is in progress.
            }

            throw new GitConflictException(
                "Rebase conflict for site [{$site->slug}]. Manual resolution required.",
                previous: $e
            );
        }
    }

    private function shouldSkipPath(string $path): bool
    {
        $skipPrefixes = [
            '.git/',
            'node_modules/',
            'vendor/',
            '.cache/',
            '.next/',
            '.nuxt/',
            '__pycache__/',
            '.svelte-kit/',
        ];

        foreach ($skipPrefixes as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return str_starts_with($path, '.') && ! str_contains($path, '/');
    }

    private function startOperation(Site $site, string $operation, ?EditSession $session = null, array $data = []): GitOperation
    {
        return GitOperation::create([
            'site_id' => $site->id,
            'edit_session_id' => $session?->id,
            'operation' => $operation,
            'status' => 'started',
            'branch' => $data['branch'] ?? $site->branch,
            'working_branch' => $data['working_branch'] ?? $session?->working_branch,
            'commit_sha' => $data['commit_sha'] ?? null,
            'files' => $data['files'] ?? null,
            'metadata' => $data['metadata'] ?? null,
            'started_at' => now(),
        ]);
    }

    private function finishOperation(
        GitOperation $operation,
        string $status,
        ?string $commitSha = null,
        ?string $output = null,
        ?string $error = null,
    ): void {
        $operation->update([
            'status' => $status,
            'commit_sha' => $commitSha ?: $operation->commit_sha,
            'output_log' => $output,
            'error_output' => $error,
            'completed_at' => now(),
        ]);
    }

    private function stringifyOutput(array|string|null $output): ?string
    {
        if (is_array($output)) {
            return implode("\n", array_map(static fn ($line) => trim((string) $line), $output));
        }

        return $output !== null ? trim((string) $output) : null;
    }
}
