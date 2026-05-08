<?php

namespace App\Services;

use App\Models\Site;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class PagePreviewService
{
    public function __construct(
        private SiteRuntimeService $runtime,
    ) {}

    public function findBuiltHtmlPath(Site $site, ?string $urlPath): ?string
    {
        $path = parse_url($urlPath ?? '/', PHP_URL_PATH) ?: '/';

        if (str_contains($path, ':') || str_contains($path, '..')) {
            return null;
        }

        $relativePath = trim($path, '/');
        $candidates = $relativePath === ''
            ? ['index.html']
            : ["{$relativePath}.html", "{$relativePath}/index.html"];

        foreach ($this->staticOutputDirs($site) as $outputDir) {
            foreach ($candidates as $candidate) {
                $repoRelativePath = trim($outputDir.'/'.$candidate, '/');
                if ($this->repoFileExists($site, $repoRelativePath)) {
                    return $repoRelativePath;
                }
            }
        }

        return null;
    }

    public function staticOutputDirs(Site $site): array
    {
        if ($this->runtime->usesRuntimeServer($site)) {
            return [];
        }

        $dirs = [];

        if ($site->build_output_dir && $site->build_output_dir !== '.next') {
            $dirs[] = $this->normalizeRepoRelativePath($site->build_output_dir);
        }

        $dirs = array_merge($dirs, $this->defaultOutputDirsForProjectType((string) $site->project_type));

        return array_values(array_unique(array_filter($dirs)));
    }

    public function contextForRepoRelativePath(Site $site, string $repoRelativePath): array
    {
        $normalizedPath = $this->normalizeRepoRelativePath($repoRelativePath);

        if ($normalizedPath === null) {
            throw new \RuntimeException('Preview path is outside of the repository.');
        }

        $repoRoot = realpath((string) $site->repo_path);
        if ($repoRoot === false) {
            throw new \RuntimeException('Repository path is unavailable.');
        }

        $repoRoot = $this->normalizeAbsolutePath($repoRoot);
        $filePath = $repoRoot.'/'.$normalizedPath;
        $realFilePath = realpath($filePath);

        if (
            $realFilePath !== false
            && $this->normalizeAbsolutePath($realFilePath) !== $repoRoot
            && ! str_starts_with($this->normalizeAbsolutePath($realFilePath), $repoRoot.'/')
        ) {
            throw new \RuntimeException('Preview file resolves outside of the repository.');
        }

        $directoryPrefix = dirname($normalizedPath);

        if ($directoryPrefix === '.') {
            $directoryPrefix = '';
        }

        return [
            'file_path' => $filePath,
            'root_prefix' => $this->rootPrefixForPath($site, $normalizedPath),
            'directory_prefix' => $directoryPrefix,
        ];
    }

    private function repoFileExists(Site $site, string $repoRelativePath): bool
    {
        try {
            $context = $this->contextForRepoRelativePath($site, $repoRelativePath);
        } catch (\Throwable) {
            return false;
        }

        $realPath = realpath($context['file_path']);

        return $realPath !== false && File::isFile($realPath);
    }

    private function normalizeRepoRelativePath(?string $path): ?string
    {
        $normalized = trim(str_replace('\\', '/', (string) $path), '/');

        if ($normalized === '') {
            return '';
        }

        if (str_contains($normalized, ':')) {
            return null;
        }

        $segments = array_filter(explode('/', $normalized), fn (string $segment): bool => $segment !== '');

        foreach ($segments as $segment) {
            if ($segment === '.' || $segment === '..') {
                return null;
            }
        }

        return implode('/', $segments);
    }

    private function normalizeAbsolutePath(string $path): string
    {
        return rtrim(str_replace('\\', '/', $path), '/');
    }

    private function rootPrefixForPath(Site $site, string $normalizedPath): string
    {
        foreach ($this->staticOutputDirs($site) as $outputDir) {
            if ($normalizedPath === $outputDir || Str::startsWith($normalizedPath, "{$outputDir}/")) {
                return $outputDir;
            }
        }

        if ($normalizedPath === 'public' || Str::startsWith($normalizedPath, 'public/')) {
            return 'public';
        }

        return '';
    }

    /**
     * @return list<string>
     */
    private function defaultOutputDirsForProjectType(string $projectType): array
    {
        return match ($projectType) {
            'nextjs' => ['out'],
            'nuxt' => ['.output/public', 'dist'],
            'astro' => ['dist'],
            'hugo' => ['public'],
            'eleventy' => ['_site'],
            'react' => ['dist', 'build', 'public'],
            'vue' => ['dist', 'public'],
            'svelte' => ['build', 'public'],
            'static_html', 'php_site' => ['public'],
            default => ['dist', 'build', 'public'],
        };
    }
}
