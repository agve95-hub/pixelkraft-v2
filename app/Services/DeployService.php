<?php

namespace App\Services;

use App\Jobs\ParseSiteJob;
use App\Models\DeployLog;
use App\Models\Site;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class DeployService
{
    public function __construct(
        private GitSyncService $git,
        private NginxConfigService $nginx,
        private ImageOptimizer $imageOptimizer,
        private HtmlMinifier $minifier,
        private SiteRuntimeService $runtime,
    ) {}

    /**
     * Run the full deploy pipeline for a site.
     */
    public function deploy(Site $site, string $triggeredBy = 'manual'): DeployLog
    {
        $log = DeployLog::create([
            'site_id'      => $site->id,
            'status'       => 'started',
            'triggered_by' => $triggeredBy,
            'created_at'   => now(),
        ]);

        $startTime = microtime(true);
        $site->update(['deploy_status' => 'building']);

        try {
            // Step 1: Pull latest
            $log->appendLog('[1/6] Pulling latest changes...');
            $this->pullLatest($site, $log);

            // Step 2: Install dependencies
            $log->appendLog('[2/6] Installing dependencies...');
            $this->installDependencies($site, $log);

            // Step 3: Build
            $log->appendLog('[3/6] Running build...');
            $this->runBuild($site, $log);

            // Step 4: Optimize
            $log->appendLog('[4/6] Optimizing assets...');
            $this->optimizeAssets($site, $log);

            // Step 5: Deploy / start runtime
            $site->update(['deploy_status' => 'deploying']);
            if ($this->runtime->usesRuntimeServer($site)) {
                $log->appendLog('[5/6] Starting runtime server...');
                $this->deployRuntime($site, $log);
            } else {
                $log->appendLog('[5/6] Deploying static files...');
                $this->deployFiles($site, $log);
            }

            // Step 6: Reload Nginx
            if ($this->shouldReloadNginx($site)) {
                $log->appendLog('[6/6] Reloading Nginx...');
                $this->nginx->reloadNginx();
            } else {
                $log->appendLog('[6/6] Skipping Nginx reload (no site config generated).');
            }

            // Record commit info
            $sha = $this->git->isCloned($site)
                ? $this->git->getCurrentSha($this->openRepo($site))
                : null;

            $duration = (int) ((microtime(true) - $startTime) * 1000);

            $log->update([
                'status'         => 'success',
                'commit_sha'     => $sha,
                'duration_ms'    => $duration,
                'snapshot_tag'   => 'deploy-' . now()->format('Ymd-His'),
            ]);

            $log->appendLog("Deploy successful in {$log->durationFormatted()}");

            $site->update([
                'deploy_status'    => 'live',
                'last_deployed_at' => now(),
            ]);

            ParseSiteJob::dispatch($site);

            // Create rollback tag
            if ($sha && $this->git->isCloned($site)) {
                try {
                    $this->git->createTag($site, $log->snapshot_tag);
                } catch (\Throwable $e) {
                    // Non-fatal: tag creation failure shouldn't fail deploy
                    Log::warning("Failed to create rollback tag for [{$site->slug}]", ['error' => $e->getMessage()]);
                }
            }

            // Clean old deploy snapshots
            $this->cleanOldSnapshots($site);

            return $log;

        } catch (\Throwable $e) {
            $duration = (int) ((microtime(true) - $startTime) * 1000);

            $log->appendLog("FAILED: {$e->getMessage()}");
            $log->update([
                'status'      => 'failed',
                'duration_ms' => $duration,
            ]);

            $site->update(['deploy_status' => 'failed']);

            Log::error("Deploy failed for [{$site->slug}]", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Rollback a site to a previous deploy snapshot.
     */
    public function rollback(Site $site, DeployLog $targetDeploy): DeployLog
    {
        if (! $targetDeploy->snapshot_tag) {
            throw new \RuntimeException('Deploy has no rollback snapshot tag.');
        }

        $log = DeployLog::create([
            'site_id'        => $site->id,
            'status'         => 'started',
            'triggered_by'   => 'manual',
            'commit_message' => "Rollback to {$targetDeploy->snapshot_tag}",
            'created_at'     => now(),
        ]);

        $startTime = microtime(true);

        try {
            $site->update(['deploy_status' => 'deploying']);

            $log->appendLog("Rolling back to {$targetDeploy->snapshot_tag}...");

            // Checkout the tagged commit
            $this->git->checkout($site, $targetDeploy->snapshot_tag);

            if ($this->runtime->usesRuntimeServer($site)) {
                $this->deployRuntime($site, $log);
            } else {
                $this->deployFiles($site, $log);
            }

            // Reload nginx
            if ($this->shouldReloadNginx($site)) {
                $this->nginx->reloadNginx();
            } else {
                $log->appendLog('Skipping Nginx reload (no site config generated).');
            }

            // Checkout back to branch head
            $this->git->checkout($site, $site->branch);

            $duration = (int) ((microtime(true) - $startTime) * 1000);

            $log->update([
                'status'       => 'success',
                'commit_sha'   => $targetDeploy->commit_sha,
                'duration_ms'  => $duration,
                'snapshot_tag' => $targetDeploy->snapshot_tag,
            ]);

            $log->appendLog("Rollback successful in {$log->durationFormatted()}");

            $site->update([
                'deploy_status'    => 'live',
                'last_deployed_at' => now(),
            ]);

            return $log;

        } catch (\Throwable $e) {
            $duration = (int) ((microtime(true) - $startTime) * 1000);

            $log->appendLog("ROLLBACK FAILED: {$e->getMessage()}");
            $log->update(['status' => 'failed', 'duration_ms' => $duration]);

            $site->update(['deploy_status' => 'failed']);
            throw $e;
        }
    }

    // ── Pipeline Steps ──────────────────────────

    private function pullLatest(Site $site, DeployLog $log): void
    {
        if (! $this->git->isCloned($site)) {
            $log->appendLog('  Repository not cloned, skipping pull.');
            return;
        }

        $hasChanges = $this->git->pull($site);
        $log->appendLog($hasChanges ? '  Pulled new changes.' : '  Already up to date.');
    }

    private function installDependencies(Site $site, DeployLog $log): void
    {
        $repoPath = $site->repo_path;

        // Node.js dependencies
        if (File::exists("{$repoPath}/package.json")) {
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
        } else {
            $log->appendLog('  No package.json found, skipping npm install.');
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
        if ($this->runtime->usesRuntimeServer($site)) {
            $log->appendLog('  Skipping static post-processing for runtime-managed build output.');
            return;
        }

        $outputDir = $this->resolveOutputDir($site);

        if (! File::isDirectory($outputDir)) {
            $log->appendLog('  No output directory found, skipping optimization.');
            return;
        }

        // Image optimization
        $imageCount = $this->imageOptimizer->optimizeDirectory($outputDir);
        $log->appendLog("  Optimized {$imageCount} images.");

        if ($this->supportsAggressiveOptimization($site)) {
            // HTML/CSS/JS minification is only safe for plain static/SSG sites.
            $minifiedCount = $this->minifier->minifyDirectory($outputDir);
            $log->appendLog("  Minified {$minifiedCount} files.");

            // Lazy loading injection is also limited to static markup.
            $lazyCount = $this->minifier->injectLazyLoading($outputDir);
            $log->appendLog("  Injected lazy loading on {$lazyCount} images.");
        } else {
            $log->appendLog('  Skipping HTML/JS minification and lazy-loading injection for framework-managed output.');
        }
    }

    private function deployFiles(Site $site, DeployLog $log): void
    {
        $sourceDir = $this->resolveOutputDir($site);
        $deployPath = $site->deploy_path;

        if (! File::isDirectory($sourceDir)) {
            throw new \RuntimeException("Output directory not found: {$sourceDir}");
        }

        $this->replaceDirectory($sourceDir, $deployPath);

        $log->appendLog("  Files deployed to {$deployPath}");
    }

    private function deployRuntime(Site $site, DeployLog $log): void
    {
        $this->runtime->deploy($site, $log);
        $log->appendLog('  Runtime site deployed on ' . $this->runtime->baseUrl($site));
    }

    // ── Helpers ──────────────────────────────────

    private function resolveOutputDir(Site $site): string
    {
        $repoPath = $site->repo_path;

        if ($site->project_type === 'nextjs') {
            return $this->resolveNextjsOutputDir($site);
        }

        if ($site->build_output_dir) {
            $outputPath = "{$repoPath}/{$site->build_output_dir}";
            if (File::isDirectory($outputPath)) {
                return $outputPath;
            }
        }

        // For static sites with no build, serve from repo root (or public/)
        if (File::isDirectory("{$repoPath}/public")) {
            return "{$repoPath}/public";
        }

        return $repoPath;
    }

    private function resolveNextjsOutputDir(Site $site): string
    {
        $repoPath = $site->repo_path;
        $configuredOutputDir = $site->build_output_dir;

        if ($configuredOutputDir && $configuredOutputDir !== '.next') {
            $configuredPath = "{$repoPath}/{$configuredOutputDir}";
            if (File::isDirectory($configuredPath)) {
                return $configuredPath;
            }
        }

        $staticCandidates = [
            "{$repoPath}/out",
        ];

        foreach ($staticCandidates as $candidate) {
            if (File::isDirectory($candidate)) {
                return $candidate;
            }
        }

        if ($configuredOutputDir === '.next' || File::isDirectory("{$repoPath}/.next")) {
            throw new \RuntimeException(
                'Next.js built a `.next` directory, which is not a static deploy artifact. ' .
                'Configure static export so the build outputs to `out`, or update the site build output directory to the exported folder.'
            );
        }

        throw new \RuntimeException(
            'No deployable Next.js output was found. Expected a static export directory such as `out` after the build finished.'
        );
    }

    private function runCommand(
        string $command,
        string $cwd,
        Site $site,
        int $timeout = 120,
        array $envOverrides = [],
    ): array
    {
        $nodeBinPath = str_replace('\\', '/', "{$cwd}/node_modules/.bin");
        $systemPath = getenv('PATH') ?: ($_SERVER['PATH'] ?? '');

        // Build environment variables
        $env = array_merge(
            [
                'NODE_ENV' => 'production',
                'PATH' => $nodeBinPath . PATH_SEPARATOR . $systemPath,
            ],
            $site->env_variables ?? [],
            $envOverrides,
        );

        // Use nvm if node version is specified
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
            'output'  => $output,
            'summary' => $result->successful() ? 'OK' : 'FAILED (exit ' . $result->exitCode() . ')',
        ];
    }

    private function openRepo(Site $site)
    {
        return (new \CzProject\GitPhp\Git())->open($site->repo_path);
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

    private function supportsAggressiveOptimization(Site $site): bool
    {
        return in_array($site->project_type, ['static_html', 'hugo', 'eleventy'], true);
    }

    private function replaceDirectory(string $sourceDir, string $targetDir): void
    {
        $stagingDir = $targetDir . '.__pixelkraft_tmp';

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
}
