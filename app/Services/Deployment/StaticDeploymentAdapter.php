<?php

namespace App\Services\Deployment;

use App\Models\DeployLog;
use App\Models\Site;
use App\Services\SiteRuntimeService;
use Illuminate\Support\Facades\File;

class StaticDeploymentAdapter implements DeploymentAdapter
{
    public function mode(): string
    {
        return SiteRuntimeService::MODE_STATIC;
    }

    public function activationStepLabel(Site $site): string
    {
        return 'Deploying static files...';
    }

    public function artifactDirectory(Site $site): ?string
    {
        return $this->resolveArtifactDirectory($site, strict: false);
    }

    public function supportsAggressiveOptimization(Site $site): bool
    {
        return in_array($site->project_type, ['static_html', 'hugo', 'eleventy'], true);
    }

    public function activate(Site $site, DeployLog $log): void
    {
        $sourceDir = $this->resolveArtifactDirectory($site, strict: true);
        $deployPath = $site->deploy_path;

        if (! $deployPath) {
            throw new \RuntimeException('No deploy path configured for static deployment.');
        }

        $this->replaceDirectory($sourceDir, $deployPath);

        $log->appendLog("  Files deployed to {$deployPath}");
    }

    private function resolveArtifactDirectory(Site $site, bool $strict): ?string
    {
        $repoPath = $site->repo_path;

        if ($site->project_type === 'nextjs') {
            return $this->resolveNextjsStaticOutputDir($site, $strict);
        }

        if ($site->build_output_dir) {
            $outputPath = "{$repoPath}/{$site->build_output_dir}";
            if (File::isDirectory($outputPath)) {
                return $outputPath;
            }
        }

        if (File::isDirectory("{$repoPath}/public")) {
            return "{$repoPath}/public";
        }

        if (File::isDirectory($repoPath)) {
            return $repoPath;
        }

        if ($strict) {
            throw new \RuntimeException("Static deployment source directory was not found for [{$site->slug}].");
        }

        return null;
    }

    private function resolveNextjsStaticOutputDir(Site $site, bool $strict): ?string
    {
        $repoPath = $site->repo_path;
        $configuredOutputDir = $site->build_output_dir;

        if ($configuredOutputDir && $configuredOutputDir !== '.next') {
            $configuredPath = "{$repoPath}/{$configuredOutputDir}";
            if (File::isDirectory($configuredPath)) {
                return $configuredPath;
            }
        }

        foreach (["{$repoPath}/out"] as $candidate) {
            if (File::isDirectory($candidate)) {
                return $candidate;
            }
        }

        if (! $strict) {
            return null;
        }

        if ($configuredOutputDir === '.next' || File::isDirectory("{$repoPath}/.next")) {
            throw new \RuntimeException(
                'Next.js built a `.next` directory, which is not a static deploy artifact. '
                .'Configure static export so the build outputs to `out`, or switch the site deployment mode to runtime.'
            );
        }

        throw new \RuntimeException(
            'No deployable Next.js static output was found. Expected a directory such as `out` after the build finished.'
        );
    }

    private function replaceDirectory(string $sourceDir, string $targetDir): void
    {
        $stagingDir = $targetDir.'.__pixelkraft_tmp';

        File::deleteDirectory($stagingDir);
        File::ensureDirectoryExists(dirname($targetDir), 0755, true);

        if (! File::copyDirectory($sourceDir, $stagingDir)) {
            throw new \RuntimeException("Failed to stage files from {$sourceDir}");
        }

        File::deleteDirectory($targetDir);

        if (! File::moveDirectory($stagingDir, $targetDir)) {
            File::deleteDirectory($stagingDir);
            throw new \RuntimeException("Failed to activate staged deploy at {$targetDir}");
        }
    }
}
