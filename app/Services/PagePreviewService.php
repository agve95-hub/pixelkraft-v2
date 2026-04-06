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

        if (str_contains($path, ':')) {
            return null;
        }

        $relativePath = trim($path, '/');
        $candidates = $relativePath === ''
            ? ['index.html']
            : ["{$relativePath}.html", "{$relativePath}/index.html"];

        foreach ($this->staticOutputDirs($site) as $outputDir) {
            foreach ($candidates as $candidate) {
                $repoRelativePath = trim($outputDir . '/' . $candidate, '/');
                if (File::exists("{$site->repo_path}/{$repoRelativePath}")) {
                    return $repoRelativePath;
                }
            }
        }

        return null;
    }

    public function staticOutputDirs(Site $site): array
    {
        $dirs = [];

        if ($site->build_output_dir && $site->build_output_dir !== '.next') {
            $dirs[] = trim($site->build_output_dir, '/');
        }

        if ($site->project_type === 'nextjs' && ! $this->runtime->usesRuntimeServer($site)) {
            $dirs[] = 'out';
        }

        if ($site->project_type === 'nuxt') {
            $dirs[] = '.output/public';
        }

        return array_values(array_unique(array_filter($dirs)));
    }

    public function contextForRepoRelativePath(Site $site, string $repoRelativePath): array
    {
        $normalizedPath = trim(str_replace('\\', '/', $repoRelativePath), '/');
        $directoryPrefix = dirname($normalizedPath);

        if ($directoryPrefix === '.') {
            $directoryPrefix = '';
        }

        return [
            'file_path' => "{$site->repo_path}/{$normalizedPath}",
            'root_prefix' => $this->rootPrefixForPath($site, $normalizedPath),
            'directory_prefix' => $directoryPrefix,
        ];
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
}
