<?php

namespace App\Livewire\Seo;

use App\Models\Site;
use App\Services\GitSyncService;
use App\Services\SiteRuntimeService;
use App\Support\SiteAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class RobotsTxtEditor extends Component
{
    public string $siteId;

    public string $content = '';

    public bool $exists = false;

    public function mount(): void
    {
        $site = SiteAccess::findOrFail($this->siteId);
        $path = $this->getRobotsPath($site);

        if ($path && File::exists($path)) {
            $this->content = File::get($path);
            $this->exists = true;
        } else {
            $this->content = $this->defaultRobotsTxt($site);
        }
    }

    public function save(): void
    {
        $this->validate(['content' => 'required|string|max:65535']);

        $site = SiteAccess::findOrFail($this->siteId);
        $git = app(GitSyncService::class);

        if (! $git->isCloned($site)) {
            session()->flash('error', 'Repository not cloned yet.');

            return;
        }

        // Write to repo
        try {
            $targetDir = $this->targetDirectory($site);
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        $filePath = "{$targetDir}/robots.txt";
        $originalRepoContent = File::exists($filePath) ? File::get($filePath) : null;
        $deployPath = null;
        $originalDeployContent = null;

        File::ensureDirectoryExists(dirname($filePath));
        File::put($filePath, $this->content);

        // Also write to deploy path if exists
        if (! $this->runtime()->usesRuntimeServer($site) && $site->deploy_path && File::isDirectory($site->deploy_path)) {
            $deployPath = rtrim($site->deploy_path, '/\\').'/robots.txt';
            $originalDeployContent = File::exists($deployPath) ? File::get($deployPath) : null;
            File::put($deployPath, $this->content);
        }

        try {
            $relativePath = ltrim(str_replace($this->repoPath($site).'/', '', $filePath), '/');
            $git->commitAndPush($site, [$relativePath], 'Update robots.txt');
            session()->flash('success', 'robots.txt saved and pushed.');
        } catch (\Throwable $e) {
            $this->restoreFileContent($filePath, $originalRepoContent);

            if ($deployPath) {
                $this->restoreFileContent($deployPath, $originalDeployContent);
            }

            Log::error('robots.txt push failed', ['site_id' => $this->siteId, 'error' => $e->getMessage()]);
            session()->flash('error', 'Push failed, so robots.txt was restored locally. Check Git operations for details.');
        }

        $this->exists = true;
    }

    public function usePreset(string $preset): void
    {
        $site = SiteAccess::findOrFail($this->siteId);

        $this->content = match ($preset) {
            'allow_all' => "User-agent: *\nAllow: /\n\nSitemap: https://{$site->domain}/sitemap.xml",
            'block_ai' => "User-agent: *\nAllow: /\n\nUser-agent: GPTBot\nDisallow: /\n\nUser-agent: ChatGPT-User\nDisallow: /\n\nUser-agent: Google-Extended\nDisallow: /\n\nUser-agent: CCBot\nDisallow: /\n\nUser-agent: anthropic-ai\nDisallow: /\n\nSitemap: https://{$site->domain}/sitemap.xml",
            'block_all' => "User-agent: *\nDisallow: /",
            default => $this->content,
        };
    }

    public function render(): View
    {
        return view('livewire.seo.robots-txt-editor');
    }

    private function getRobotsPath(Site $site): ?string
    {
        if (! $site->repo_path || ! File::isDirectory($site->repo_path)) {
            return null;
        }

        $candidates = [
            "{$site->repo_path}/robots.txt",
        ];

        try {
            array_unshift($candidates, $this->targetDirectory($site).'/robots.txt');
        } catch (\Throwable) {
            return null;
        }

        foreach ($candidates as $path) {
            if (File::exists($path)) {
                return $path;
            }
        }

        return $candidates[0];
    }

    private function defaultRobotsTxt(Site $site): string
    {
        $domain = $site->domain ?? 'example.com';

        return "User-agent: *\nAllow: /\n\nSitemap: https://{$domain}/sitemap.xml";
    }

    private function targetDirectory(Site $site): string
    {
        $repoPath = $this->repoPath($site);
        $relativePath = $this->runtime()->usesRuntimeServer($site)
            ? 'public'
            : (string) ($site->build_output_dir ?: '');

        $relativePath = $this->normalizeRelativePath($relativePath);
        $targetDir = $relativePath === ''
            ? $repoPath
            : $this->normalizeAbsolutePath($repoPath.'/'.$relativePath);

        if ($targetDir !== $repoPath && ! str_starts_with($targetDir, $repoPath.'/')) {
            throw new \RuntimeException('Refusing to write robots.txt outside of the repository.');
        }

        return $targetDir;
    }

    private function repoPath(Site $site): string
    {
        $resolved = realpath((string) $site->repo_path);

        if ($resolved === false) {
            throw new \RuntimeException('Repository path is unavailable.');
        }

        return $this->normalizeAbsolutePath($resolved);
    }

    private function normalizeRelativePath(string $path): string
    {
        $normalized = trim(str_replace('\\', '/', $path), '/');

        if ($normalized === '') {
            return '';
        }

        $segments = array_filter(explode('/', $normalized), fn (string $segment): bool => $segment !== '');

        foreach ($segments as $segment) {
            if ($segment === '.' || $segment === '..') {
                throw new \RuntimeException('Build output directory contains an unsafe path segment.');
            }
        }

        return implode('/', $segments);
    }

    private function normalizeAbsolutePath(string $path): string
    {
        return rtrim(str_replace('\\', '/', $path), '/');
    }

    private function restoreFileContent(string $path, ?string $content): void
    {
        if ($content === null) {
            File::delete($path);

            return;
        }

        File::put($path, $content);
    }

    private function runtime(): SiteRuntimeService
    {
        return app(SiteRuntimeService::class);
    }
}
