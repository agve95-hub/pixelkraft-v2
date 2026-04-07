<?php

namespace App\Livewire\Seo;

use App\Models\Site;
use App\Services\GitSyncService;
use App\Services\SiteRuntimeService;
use Illuminate\Support\Facades\File;
use Livewire\Component;

class RobotsTxtEditor extends Component
{
    public string $siteId;
    public string $content = '';
    public bool $exists = false;

    public function mount(): void
    {
        $site = Site::findOrFail($this->siteId);
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
        $site = Site::findOrFail($this->siteId);
        $git = app(GitSyncService::class);

        if (! $git->isCloned($site)) {
            session()->flash('error', 'Repository not cloned yet.');
            return;
        }

        // Write to repo
        $targetDir = $this->targetDirectory($site);
        $filePath = "{$targetDir}/robots.txt";

        File::ensureDirectoryExists(dirname($filePath));
        File::put($filePath, $this->content);

        // Also write to deploy path if exists
        if (! $this->runtime()->usesRuntimeServer($site) && $site->deploy_path && File::isDirectory($site->deploy_path)) {
            File::put("{$site->deploy_path}/robots.txt", $this->content);
        }

        try {
            $relativePath = str_replace($site->repo_path . '/', '', $filePath);
            $git->commitAndPush($site, [$relativePath], 'Update robots.txt');
            session()->flash('success', 'robots.txt saved and pushed.');
        } catch (\Throwable $e) {
            session()->flash('error', 'Saved locally but push failed: ' . $e->getMessage());
        }

        $this->exists = true;
    }

    public function usePreset(string $preset): void
    {
        $site = Site::findOrFail($this->siteId);

        $this->content = match ($preset) {
            'allow_all' => "User-agent: *\nAllow: /\n\nSitemap: https://{$site->domain}/sitemap.xml",
            'block_ai'  => "User-agent: *\nAllow: /\n\nUser-agent: GPTBot\nDisallow: /\n\nUser-agent: ChatGPT-User\nDisallow: /\n\nUser-agent: Google-Extended\nDisallow: /\n\nUser-agent: CCBot\nDisallow: /\n\nUser-agent: anthropic-ai\nDisallow: /\n\nSitemap: https://{$site->domain}/sitemap.xml",
            'block_all' => "User-agent: *\nDisallow: /",
            default     => $this->content,
        };
    }

    public function render()
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

        array_unshift($candidates, $this->targetDirectory($site) . '/robots.txt');

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
        if ($this->runtime()->usesRuntimeServer($site)) {
            return "{$site->repo_path}/public";
        }

        return $site->build_output_dir
            ? "{$site->repo_path}/{$site->build_output_dir}"
            : $site->repo_path;
    }

    private function runtime(): SiteRuntimeService
    {
        return app(SiteRuntimeService::class);
    }
}
