<?php

namespace App\Services;

use App\Models\Site;
use CzProject\GitPhp\Git;
use CzProject\GitPhp\GitRepository;
use CzProject\GitPhp\GitException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class GitSyncService
{
    private Git $git;

    public function __construct()
    {
        $this->git = new Git();
    }

    /**
     * Clone a repository for a site.
     */
    public function cloneRepo(Site $site): GitRepository
    {
        $repoPath = $site->repo_path;

        if (File::isDirectory($repoPath)) {
            File::deleteDirectory($repoPath);
        }

        File::ensureDirectoryExists(dirname($repoPath), 0755, true);

        $authUrl = $this->buildAuthUrl($site);

        Log::info("Cloning repo for site [{$site->slug}]", [
            'repo_url' => $site->repo_url,
            'branch'   => $site->branch,
            'path'     => $repoPath,
        ]);

        $repo = $this->git->cloneRepository($authUrl, $repoPath, [
            '-b' => $site->branch,
            '--single-branch',
            '--depth' => 50,
        ]);

        $site->update([
            'last_synced_at' => now(),
        ]);

        return $repo;
    }

    /**
     * Pull latest changes from remote.
     * Returns true if new changes were pulled, false if already up to date.
     */
    public function pull(Site $site): bool
    {
        $repo = $this->openRepo($site);
        $beforeSha = $this->getCurrentSha($repo);

        $this->configureAuth($repo, $site);

        try {
            $repo->pull('origin', [$site->branch]);
        } catch (GitException $e) {
            if ($this->isConflict($e)) {
                Log::warning("Merge conflict pulling site [{$site->slug}]", [
                    'error' => $e->getMessage(),
                ]);
                $repo->execute('merge', '--abort');
                throw new GitConflictException(
                    "Merge conflict detected for site [{$site->slug}]. Remote changes conflict with local edits.",
                    previous: $e
                );
            }
            throw $e;
        }

        $afterSha = $this->getCurrentSha($repo);

        $site->update([
            'last_synced_at' => now(),
        ]);

        return $beforeSha !== $afterSha;
    }

    /**
     * Stage files, commit, and push to remote.
     */
    public function commitAndPush(Site $site, array $files, string $message): string
    {
        $repo = $this->openRepo($site);
        $this->configureAuth($repo, $site);

        // Stage specific files
        foreach ($files as $file) {
            $repo->addFile($file);
        }

        // Check if there are changes to commit
        if (! $this->hasChanges($repo)) {
            Log::info("No changes to commit for site [{$site->slug}]");
            return $this->getCurrentSha($repo);
        }

        $repo->commit($message);

        try {
            $repo->push('origin', [$site->branch]);
        } catch (GitException $e) {
            if ($this->isPushRejected($e)) {
                Log::info("Push rejected for [{$site->slug}], attempting pull + rebase");
                return $this->pullRebaseAndPush($site, $repo);
            }
            throw $e;
        }

        $sha = $this->getCurrentSha($repo);

        Log::info("Pushed commit for site [{$site->slug}]", [
            'sha'     => $sha,
            'message' => $message,
            'files'   => $files,
        ]);

        return $sha;
    }

    /**
     * Stage all changed files, commit, and push.
     */
    public function commitAllAndPush(Site $site, string $message): string
    {
        $repo = $this->openRepo($site);
        $this->configureAuth($repo, $site);

        $repo->addAllChanges();

        if (! $this->hasChanges($repo)) {
            return $this->getCurrentSha($repo);
        }

        $repo->commit($message);

        try {
            $repo->push('origin', [$site->branch]);
        } catch (GitException $e) {
            if ($this->isPushRejected($e)) {
                return $this->pullRebaseAndPush($site, $repo);
            }
            throw $e;
        }

        $sha = $this->getCurrentSha($repo);

        Log::info("Pushed all changes for site [{$site->slug}]", [
            'sha'     => $sha,
            'message' => $message,
        ]);

        return $sha;
    }

    /**
     * Get the list of files changed between two commits.
     */
    public function getChangedFiles(Site $site, string $fromSha, string $toSha): array
    {
        $repo = $this->openRepo($site);

        $output = $repo->execute('diff', '--name-only', $fromSha, $toSha);

        return array_filter(array_map('trim', $output));
    }

    /**
     * Get current HEAD SHA.
     */
    public function getCurrentSha(GitRepository $repo): string
    {
        $output = $repo->execute('rev-parse', 'HEAD');

        return trim($output[0] ?? '');
    }

    /**
     * Get short log of recent commits.
     */
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
            $parts = explode('||', trim($line));
            if (count($parts) === 4) {
                $commits[] = [
                    'sha'     => $parts[0],
                    'message' => $parts[1],
                    'date'    => $parts[2],
                    'author'  => $parts[3],
                ];
            }
        }

        return $commits;
    }

    /**
     * Create a tag for rollback snapshots.
     */
    public function createTag(Site $site, string $tagName): void
    {
        $repo = $this->openRepo($site);
        $repo->createTag($tagName);
    }

    /**
     * Checkout a specific tag or commit for rollback.
     */
    public function checkout(Site $site, string $ref): void
    {
        $repo = $this->openRepo($site);
        $repo->checkout($ref);
    }

    /**
     * Check if the repo directory exists and is a valid git repo.
     */
    public function isCloned(Site $site): bool
    {
        return File::isDirectory($site->repo_path . '/.git');
    }

    /**
     * Get all files in the repository, filtered by extension.
     */
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

            // Skip hidden files and common non-content directories
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

    // ── Private Helpers ─────────────────────────

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
        $path = ltrim($parsed['path'] ?? '', '/');

        return "https://x-access-token:{$site->github_token}@{$host}/{$path}";
    }

    private function configureAuth(GitRepository $repo, Site $site): void
    {
        if (empty($site->github_token)) {
            return;
        }

        $authUrl = $this->buildAuthUrl($site);
        $repo->execute('remote', 'set-url', 'origin', $authUrl);
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
            $repo->push('origin', [$site->branch]);

            return $this->getCurrentSha($repo);
        } catch (GitException $e) {
            $repo->execute('rebase', '--abort');
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

        // Skip hidden files at root
        if (str_starts_with($path, '.') && ! str_contains($path, '/')) {
            return true;
        }

        return false;
    }
}
