<?php

namespace App\Services;

use App\Jobs\ParseSiteJob;
use App\Models\DeploymentRelease;
use App\Models\DeploymentTarget;
use App\Models\DeployLog;
use App\Models\Site;
use App\Services\Deployment\DeploymentAdapter;
use App\Services\Deployment\RuntimeDeploymentAdapter;
use App\Services\Deployment\StaticDeploymentAdapter;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Throwable;

class DeployService
{
    public function __construct(
        private GitSyncService $git,
        private NginxConfigService $nginx,
        private ImageOptimizer $imageOptimizer,
        private HtmlMinifier $minifier,
        private SiteRuntimeService $runtime,
        private SiteProvisioningService $provisioning,
        private TrackingScriptService $tracking,
        private StaticDeploymentAdapter $staticDeployments,
        private RuntimeDeploymentAdapter $runtimeDeployments,
    ) {}

    public function deploy(Site $site, string $triggeredBy = 'manual'): DeployLog
    {
        ['log' => $log, 'release' => $release] = $this->beginDeployment($site, $triggeredBy);

        try {
            $this->provisionEnvironment($site->fresh(), $log->fresh(), $release->fresh());
            $this->buildSite($site->fresh(), $log->fresh(), $release->fresh());
            $this->injectTrackingAssets($site->fresh(), $log->fresh(), $release->fresh());
            $this->activateRelease($site->fresh(), $log->fresh(), $release->fresh());

            return $log->fresh();
        } catch (Throwable $e) {
            $this->markDeploymentFailed($site, $log, $release, $e);

            throw $e;
        }
    }

    /**
     * @return array{log: DeployLog, release: DeploymentRelease, target: DeploymentTarget}
     */
    public function beginDeployment(Site $site, string $triggeredBy = 'manual'): array
    {
        $this->provisioning->initializeSite($site);
        $target = $this->resolveTarget($site, 'production');

        $log = DeployLog::create([
            'site_id' => $site->id,
            'status' => 'queued',
            'triggered_by' => $triggeredBy,
            'created_at' => now(),
        ]);

        $release = $this->startRelease($site, $log, $target);

        $site->update(['deploy_status' => 'queued']);
        $log->appendLog('Deploy pipeline queued.');

        return [
            'log' => $log,
            'release' => $release,
            'target' => $target,
        ];
    }

    public function provisionEnvironment(Site $site, DeployLog $log, DeploymentRelease $release): void
    {
        $site->update(['deploy_status' => 'building']);

        $target = $release->deploymentTarget()->first() ?: $this->resolveTarget($site, 'production');

        $log->appendLog('[1/4] Provisioning site environment...');
        $this->provisioning->initializeSite($site);

        File::ensureDirectoryExists(dirname((string) $site->deploy_path), 0755, true);

        if ($target->deploy_path) {
            File::ensureDirectoryExists(dirname((string) $target->deploy_path), 0755, true);
        }

        $release->update([
            'status' => 'provisioned',
            'artifact_path' => $release->artifact_path ?: ($this->adapterFor($site)->artifactDirectory($site) ?: $target->deploy_path),
            'meta' => array_merge($release->meta ?? [], [
                'target_environment' => $target->environment,
                'runtime_type' => $target->runtime_type,
                'release_strategy' => $target->release_strategy,
                'provisioned_at' => now()->toIso8601String(),
            ]),
        ]);

        $log->appendLog('  Deployment target: ' . $target->environment . ' (' . ($target->runtime_type ?: 'static') . ')');
        $log->appendLog('  Release strategy: ' . ($target->release_strategy ?: 'symlink'));
        $log->appendLog('  Repo path: ' . ($site->repo_path ?: 'n/a'));
        $log->appendLog('  Deploy path: ' . ($target->deploy_path ?: $site->deploy_path ?: 'n/a'));
    }

    public function buildSite(Site $site, DeployLog $log, DeploymentRelease $release): void
    {
        $log->appendLog('[2/4] Pulling, installing dependencies, and building...');

        $this->pullLatest($site, $log);
        $this->installDependencies($site, $log);
        $this->runBuild($site, $log);
        $this->optimizeAssets($site, $log);

        $release->update([
            'status' => 'built',
            'artifact_path' => $this->adapterFor($site)->artifactDirectory($site) ?: $release->artifact_path,
            'meta' => array_merge($release->meta ?? [], [
                'built_at' => now()->toIso8601String(),
            ]),
        ]);
    }

    public function injectTrackingAssets(Site $site, DeployLog $log, DeploymentRelease $release): void
    {
        $log->appendLog('[3/4] Injecting tracking and release metadata...');

        $this->injectTracking($site, $log, $release);

        $release->update([
            'status' => 'tracked',
            'meta' => array_merge($release->meta ?? [], [
                'tracking_injected_at' => now()->toIso8601String(),
            ]),
        ]);
    }

    public function activateRelease(Site $site, DeployLog $log, DeploymentRelease $release): void
    {
        $site->update(['deploy_status' => 'deploying']);

        $target = $release->deploymentTarget()->first() ?: $this->resolveTarget($site, 'production');
        $adapter = $this->adapterFor($site);

        $log->appendLog('[4/4] ' . $adapter->activationStepLabel($site));
        $adapter->activate($site, $log);

        if ($this->shouldReloadNginx($site)) {
            $log->appendLog('  Reloading Nginx...');
            $this->nginx->reloadNginx();
        } else {
            $log->appendLog('  Skipping Nginx reload (no site config generated).');
        }

        $sha = $this->git->isCloned($site)
            ? $this->git->currentCommitSha($site)
            : null;

        $log->update([
            'status' => 'success',
            'commit_sha' => $sha,
            'duration_ms' => $this->elapsedDuration($log),
            'snapshot_tag' => 'deploy-' . now()->format('Ymd-His'),
        ]);

        $site->update([
            'deploy_status' => 'live',
            'last_deployed_at' => now(),
        ]);

        $this->completeRelease($site, $release, $target, $sha);
        $this->runHealthCheck($target, $log);
        ParseSiteJob::dispatch($site);

        if ($sha && $this->git->isCloned($site)) {
            try {
                $this->git->createTag($site, $log->snapshot_tag);
            } catch (Throwable $e) {
                Log::warning("Failed to create rollback tag for [{$site->slug}]", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->cleanOldSnapshots($site);
        $log->appendLog("Deploy successful in {$log->durationFormatted()}");
    }

    public function markDeploymentFailed(
        Site|string $site,
        DeployLog|string|null $log,
        DeploymentRelease|string|null $release,
        Throwable|string $error,
    ): void {
        $site = $site instanceof Site ? $site : Site::query()->findOrFail($site);
        $log = $this->resolveDeployLog($log);
        $release = $this->resolveRelease($release);
        $message = $error instanceof Throwable ? $error->getMessage() : (string) $error;

        if ($log) {
            $log->appendLog("FAILED: {$message}");
            $log->update([
                'status' => 'failed',
                'duration_ms' => $this->elapsedDuration($log),
            ]);
        }

        if ($release) {
            $release->update([
                'status' => 'failed',
                'meta' => array_merge($release->meta ?? [], ['error' => $message]),
            ]);
        }

        $site->update(['deploy_status' => 'failed']);

        Log::error("Deploy failed for [{$site->slug}]", [
            'error' => $message,
            'exception' => $error instanceof Throwable ? $error::class : null,
        ]);
    }

    public function rollback(Site $site, DeployLog $targetDeploy): DeployLog
    {
        if (! $targetDeploy->snapshot_tag) {
            throw new \RuntimeException('Deploy has no rollback snapshot tag.');
        }

        $log = DeployLog::create([
            'site_id' => $site->id,
            'status' => 'started',
            'triggered_by' => 'manual',
            'commit_message' => "Rollback to {$targetDeploy->snapshot_tag}",
            'created_at' => now(),
        ]);

        try {
            $site->update(['deploy_status' => 'deploying']);
            $target = $this->resolveTarget($site, 'production');
            $rollbackSourceReleaseId = $site->deploymentReleases()
                ->where('source_commit_sha', $targetDeploy->commit_sha)
                ->latest('activated_at')
                ->value('id');
            $release = $this->startRelease($site, $log, $target, $targetDeploy->commit_sha, $rollbackSourceReleaseId);

            $log->appendLog("Rolling back to {$targetDeploy->snapshot_tag}...");

            $this->git->checkout($site, $targetDeploy->snapshot_tag);

            $adapter = $this->adapterFor($site);
            $log->appendLog($adapter->activationStepLabel($site));
            $adapter->activate($site, $log);

            if ($this->shouldReloadNginx($site)) {
                $this->nginx->reloadNginx();
            } else {
                $log->appendLog('Skipping Nginx reload (no site config generated).');
            }

            $this->git->checkout($site, $site->branch);

            $log->update([
                'status' => 'success',
                'commit_sha' => $targetDeploy->commit_sha,
                'duration_ms' => $this->elapsedDuration($log),
                'snapshot_tag' => $targetDeploy->snapshot_tag,
            ]);

            $site->update([
                'deploy_status' => 'live',
                'last_deployed_at' => now(),
            ]);
            $this->completeRelease($site, $release, $target, $targetDeploy->commit_sha, $release->rollback_of_release_id);
            $log->appendLog("Rollback successful in {$log->durationFormatted()}");

            return $log;
        } catch (Throwable $e) {
            $this->markDeploymentFailed($site, $log, $release ?? null, $e);

            throw $e;
        }
    }

    private function pullLatest(Site $site, DeployLog $log): void
    {
        if (! $this->git->isCloned($site)) {
            $log->appendLog('  Repository not cloned. Cloning now...');

            try {
                $this->git->cloneRepo($site);
                $log->appendLog('  Clone completed.');
            } catch (Throwable $e) {
                throw new \RuntimeException("Failed to clone repository: {$e->getMessage()}");
            }

            return;
        }

        $hasChanges = $this->git->pull($site);
        $log->appendLog($hasChanges ? '  Pulled new changes.' : '  Already up to date.');
    }

    private function installDependencies(Site $site, DeployLog $log): void
    {
        $repoPath = $site->repo_path;

        if (! File::exists("{$repoPath}/package.json")) {
            $log->appendLog('  No package.json found, skipping npm install.');

            return;
        }

        $installCommand = $this->dependencyInstallCommand($repoPath);
        $result = $this->runCommand(
            $installCommand,
            $repoPath,
            $site,
            timeout: 180,
            envOverrides: [
                'NODE_ENV' => 'development',
                'NPM_CONFIG_PRODUCTION' => 'false',
                'npm_config_production' => 'false',
            ],
        );

        $this->appendCommandResult($log, '  Dependencies', $result);

        if (! $result['success']) {
            throw new \RuntimeException("Dependency installation failed: {$result['output']}");
        }
    }

    private function runBuild(Site $site, DeployLog $log): void
    {
        $buildCommand = $this->resolveBuildCommand($site);

        if (empty($buildCommand)) {
            $log->appendLog('  No build command configured, skipping build.');

            return;
        }

        $result = $this->runCommand(
            $buildCommand,
            $site->repo_path,
            $site,
            timeout: config('pixelkraft.deploy.build_timeout_seconds', 300),
        );

        $this->appendCommandResult($log, '  Build', $result);

        if (! $result['success']) {
            throw new \RuntimeException("Build failed: {$result['output']}");
        }
    }

    private function optimizeAssets(Site $site, DeployLog $log): void
    {
        $adapter = $this->adapterFor($site);
        $outputDir = $adapter->artifactDirectory($site);

        if (! $outputDir) {
            $log->appendLog(
                $adapter->mode() === SiteRuntimeService::MODE_RUNTIME
                    ? '  Skipping static post-processing for runtime-managed build output.'
                    : '  No static build artifact was found yet, skipping optimization.'
            );

            return;
        }

        if (! File::isDirectory($outputDir)) {
            $log->appendLog('  No output directory found, skipping optimization.');

            return;
        }

        $imageCount = $this->imageOptimizer->optimizeDirectory($outputDir);
        $log->appendLog("  Optimized {$imageCount} images.");

        if ($adapter->supportsAggressiveOptimization($site)) {
            $minifiedCount = $this->minifier->minifyDirectory($outputDir);
            $log->appendLog("  Minified {$minifiedCount} files.");

            $lazyCount = $this->minifier->injectLazyLoading($outputDir);
            $log->appendLog("  Injected lazy loading on {$lazyCount} images.");

            return;
        }

        $log->appendLog('  Skipping HTML/JS minification and lazy-loading injection for framework-managed output.');
    }

    private function runCommand(
        string $command,
        string $cwd,
        Site $site,
        int $timeout = 120,
        array $envOverrides = [],
    ): array {
        $nodeBinPath = str_replace('\\', '/', "{$cwd}/node_modules/.bin");
        $systemPath = getenv('PATH') ?: ($_SERVER['PATH'] ?? '');

        $env = array_merge(
            [
                'NODE_ENV' => 'production',
                'PATH' => $nodeBinPath . PATH_SEPARATOR . $systemPath,
            ],
            $site->env_variables ?? [],
            $envOverrides,
        );

        $nodeVersion = $site->node_version ?? '20';
        $nvmPrefix = "export NVM_DIR=\"\$HOME/.nvm\" && [ -s \"\$NVM_DIR/nvm.sh\" ] && . \"\$NVM_DIR/nvm.sh\" && nvm use {$nodeVersion} 2>/dev/null;";
        $fullCommand = "{$nvmPrefix} {$command}";

        $result = Process::timeout($timeout)
            ->path($cwd)
            ->env($env)
            ->run(['bash', '-c', $fullCommand]);

        $output = trim($result->output() . "\n" . $result->errorOutput());

        return [
            'success' => $result->successful(),
            'command' => $command,
            'output' => $output,
            'summary' => $result->successful() ? 'OK' : 'FAILED (exit ' . $result->exitCode() . ')',
        ];
    }

    private function injectTracking(Site $site, DeployLog $log, DeploymentRelease $release): void
    {
        $adapter = $this->adapterFor($site);
        $outputDir = $adapter->artifactDirectory($site);

        if (! $outputDir || ! File::isDirectory($outputDir)) {
            $log->appendLog('  No static artifact directory available for tracking injection.');

            return;
        }

        $count = $this->tracking->injectIntoDirectory($site, $outputDir);
        $release->update([
            'tracking_version' => 'pixelkraft-v1',
            'artifact_path' => $outputDir,
            'meta' => array_merge($release->meta ?? [], ['tracking_injected_files' => $count]),
        ]);

        $log->appendLog("  Injected tracking into {$count} HTML files.");
    }

    private function shouldReloadNginx(Site $site): bool
    {
        return ! empty($site->nginx_conf_path) && File::exists($site->nginx_conf_path);
    }

    private function dependencyInstallCommand(string $repoPath): string
    {
        return match ($this->packageManager($repoPath)) {
            'pnpm' => 'corepack pnpm install --frozen-lockfile --prod=false',
            'yarn' => 'corepack yarn install --frozen-lockfile --production=false',
            'bun' => 'bun install --frozen-lockfile',
            'npm' => File::exists("{$repoPath}/package-lock.json") || File::exists("{$repoPath}/npm-shrinkwrap.json")
                ? 'npm ci --include=dev'
                : 'npm install',
            default => 'npm install',
        };
    }

    private function resolveBuildCommand(Site $site): ?string
    {
        $buildCommand = trim((string) ($site->build_command ?? ''));

        if ($buildCommand === '') {
            return null;
        }

        $packagePath = "{$site->repo_path}/package.json";
        if (! File::exists($packagePath)) {
            return $buildCommand;
        }

        $packageJson = json_decode(File::get($packagePath), true);
        if (! is_array($packageJson)) {
            return $buildCommand;
        }

        $scripts = is_array($packageJson['scripts'] ?? null) ? $packageJson['scripts'] : [];
        $buildScript = trim((string) ($scripts['build'] ?? ''));
        $exportScript = trim((string) ($scripts['export'] ?? ''));
        $normalizedBuild = preg_replace('/\s+/', ' ', strtolower($buildCommand)) ?: strtolower($buildCommand);

        if (
            $buildScript !== '' &&
            (
                $buildCommand === $buildScript
                || preg_match('/^(?:npm run|corepack pnpm|corepack yarn|pnpm|yarn|bun run)\s+build$/', $normalizedBuild)
            )
        ) {
            return $this->packageManagerRun($site->repo_path, 'build');
        }

        if (
            $buildScript !== '' &&
            $exportScript !== '' &&
            (
                $buildCommand === "{$buildScript} && {$exportScript}"
                || preg_match('/^(?:npm run|corepack pnpm|corepack yarn|pnpm|yarn|bun run)\s+build\s+&&\s+(?:npm run|corepack pnpm|corepack yarn|pnpm|yarn|bun run)\s+export$/', $normalizedBuild)
            )
        ) {
            return $this->packageManagerRun($site->repo_path, 'build')
                . ' && ' .
                $this->packageManagerRun($site->repo_path, 'export');
        }

        return $buildCommand;
    }

    private function packageManager(string $repoPath): string
    {
        return match (true) {
            File::exists("{$repoPath}/pnpm-lock.yaml") => 'pnpm',
            File::exists("{$repoPath}/yarn.lock") => 'yarn',
            File::exists("{$repoPath}/bun.lockb"), File::exists("{$repoPath}/bun.lock") => 'bun',
            File::exists("{$repoPath}/package-lock.json"), File::exists("{$repoPath}/npm-shrinkwrap.json") => 'npm',
            default => 'npm',
        };
    }

    private function packageManagerRun(string $repoPath, string $script): string
    {
        return match ($this->packageManager($repoPath)) {
            'pnpm' => "corepack pnpm {$script}",
            'yarn' => "corepack yarn {$script}",
            'bun' => "bun run {$script}",
            default => "npm run {$script}",
        };
    }

    private function appendCommandResult(DeployLog $log, string $label, array $result): void
    {
        $log->appendLog("{$label}: {$result['summary']} ({$result['command']})");

        if (! empty($result['output'])) {
            $log->appendLog($this->indentMultilineOutput($result['output']));
        }
    }

    private function indentMultilineOutput(string $output): string
    {
        return collect(preg_split("/\r\n|\n|\r/", trim($output)) ?: [])
            ->filter(fn (?string $line) => $line !== null && $line !== '')
            ->map(fn (string $line) => '    ' . $line)
            ->take(80)
            ->implode("\n");
    }

    private function cleanOldSnapshots(Site $site): void
    {
        $maxSnapshots = config('pixelkraft.deploy.rollback_snapshots', 10);

        $oldDeploys = $site->deployLogs()
            ->where('status', 'success')
            ->whereNotNull('snapshot_tag')
            ->orderBy('created_at', 'desc')
            ->skip($maxSnapshots)
            ->take(100)
            ->get();

        foreach ($oldDeploys as $deploy) {
            $deploy->update(['snapshot_tag' => null]);
        }
    }

    private function adapterFor(Site $site): DeploymentAdapter
    {
        return match ($this->runtime->deploymentMode($site)) {
            SiteRuntimeService::MODE_RUNTIME => $this->runtimeDeployments,
            default => $this->staticDeployments,
        };
    }

    private function resolveTarget(Site $site, string $environment): DeploymentTarget
    {
        return $site->deploymentTargets()
            ->where('environment', $environment)
            ->firstOrFail();
    }

    private function startRelease(
        Site $site,
        DeployLog $log,
        DeploymentTarget $target,
        ?string $sourceCommitSha = null,
        ?string $rollbackOfReleaseId = null,
    ): DeploymentRelease {
        return DeploymentRelease::create([
            'site_id' => $site->id,
            'deployment_target_id' => $target->id,
            'deploy_log_id' => $log->id,
            'rollback_of_release_id' => $rollbackOfReleaseId,
            'source_commit_sha' => $sourceCommitSha,
            'source_branch' => $site->branch,
            'artifact_path' => $this->adapterFor($site)->artifactDirectory($site),
            'status' => 'building',
            'is_current' => false,
            'meta' => [
                'triggered_by' => $log->triggered_by,
            ],
        ]);
    }

    private function completeRelease(
        Site $site,
        DeploymentRelease $release,
        DeploymentTarget $target,
        ?string $commitSha,
        ?string $rollbackOfReleaseId = null,
    ): void {
        $site->deploymentReleases()->where('is_current', true)->update(['is_current' => false]);

        $release->update([
            'source_commit_sha' => $commitSha ?: $release->source_commit_sha,
            'status' => 'active',
            'is_current' => true,
            'activated_at' => now(),
            'artifact_path' => $release->artifact_path ?: ($this->adapterFor($site)->artifactDirectory($site) ?: $target->deploy_path),
            'meta' => array_merge($release->meta ?? [], [
                'rollback_of_release_id' => $rollbackOfReleaseId,
                'target_environment' => $target->environment,
            ]),
        ]);
    }

    private function runHealthCheck(DeploymentTarget $target, DeployLog $log): void
    {
        if (! $target->health_check_url) {
            $log->appendLog('  No health check URL configured for deployment target.');

            return;
        }

        try {
            $response = Http::timeout(15)
                ->withOptions(['http_errors' => false])
                ->get($target->health_check_url);

            $log->appendLog('  Health check: HTTP ' . $response->status() . ' (' . $target->health_check_url . ')');

            if ($response->status() >= 500) {
                throw new \RuntimeException("Health check failed with HTTP {$response->status()}");
            }
        } catch (Throwable $e) {
            $log->appendLog('  Health check failed: ' . $e->getMessage());
            throw $e;
        }
    }

    private function resolveDeployLog(DeployLog|string|null $log): ?DeployLog
    {
        if ($log instanceof DeployLog || $log === null) {
            return $log;
        }

        return DeployLog::query()->find($log);
    }

    private function resolveRelease(DeploymentRelease|string|null $release): ?DeploymentRelease
    {
        if ($release instanceof DeploymentRelease || $release === null) {
            return $release;
        }

        return DeploymentRelease::query()->find($release);
    }

    private function elapsedDuration(DeployLog $log): int
    {
        return max(0, (int) $log->created_at?->diffInMilliseconds(now()));
    }
}
