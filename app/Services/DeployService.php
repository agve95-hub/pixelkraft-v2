<?php

namespace App\Services;

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

            // Step 5: Deploy to serve directory
            $site->update(['deploy_status' => 'deploying']);
            $log->appendLog('[5/6] Deploying to serve directory...');
            $this->deployFiles($site, $log);

            // Step 6: Reload Nginx
            $log->appendLog('[6/6] Reloading Nginx...');
            $this->nginx->reloadNginx();

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

            // Re-deploy files
            $this->deployFiles($site, $log);

            // Reload nginx
            $this->nginx->reloadNginx();

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
            $lockFile = File::exists("{$repoPath}/package-lock.json") ? 'npm ci' : 'npm install';

            $result = $this->runCommand($lockFile, $repoPath, $site, timeout: 180);
            $log->appendLog("  npm: {$result['summary']}");

            if (! $result['success']) {
                throw new \RuntimeException("npm install failed: {$result['output']}");
            }
        } else {
            $log->appendLog('  No package.json found, skipping npm install.');
        }
    }

    private function runBuild(Site $site, DeployLog $log): void
    {
        if (empty($site->build_command)) {
            $log->appendLog('  No build command configured, skipping build.');
            return;
        }

        $result = $this->runCommand(
            $site->build_command,
            $site->repo_path,
            $site,
            timeout: config('pixelkraft.deploy.build_timeout_seconds', 300),
        );

        $log->appendLog("  Build: {$result['summary']}");

        if (! $result['success']) {
            throw new \RuntimeException("Build failed: {$result['output']}");
        }
    }

    private function optimizeAssets(Site $site, DeployLog $log): void
    {
        $outputDir = $this->resolveOutputDir($site);

        if (! File::isDirectory($outputDir)) {
            $log->appendLog('  No output directory found, skipping optimization.');
            return;
        }

        // Image optimization
        $imageCount = $this->imageOptimizer->optimizeDirectory($outputDir);
        $log->appendLog("  Optimized {$imageCount} images.");

        // HTML/CSS/JS minification
        $minifiedCount = $this->minifier->minifyDirectory($outputDir);
        $log->appendLog("  Minified {$minifiedCount} files.");

        // Lazy loading injection
        $lazyCount = $this->minifier->injectLazyLoading($outputDir);
        $log->appendLog("  Injected lazy loading on {$lazyCount} images.");
    }

    private function deployFiles(Site $site, DeployLog $log): void
    {
        $sourceDir = $this->resolveOutputDir($site);
        $deployPath = $site->deploy_path;

        if (! File::isDirectory($sourceDir)) {
            throw new \RuntimeException("Output directory not found: {$sourceDir}");
        }

        // Ensure deploy directory exists
        File::ensureDirectoryExists($deployPath, 0755, true);

        // Sync files (rsync-style: copy new/changed, delete removed)
        $result = Process::timeout(60)
            ->path($site->repo_path)
            ->run("rsync -a --delete {$sourceDir}/ {$deployPath}/");

        if (! $result->successful()) {
            throw new \RuntimeException("File sync failed: {$result->errorOutput()}");
        }

        $log->appendLog("  Files deployed to {$deployPath}");
    }

    // ── Helpers ──────────────────────────────────

    private function resolveOutputDir(Site $site): string
    {
        $repoPath = $site->repo_path;

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

    private function runCommand(string $command, string $cwd, Site $site, int $timeout = 120): array
    {
        // Build environment variables
        $env = array_merge(
            ['NODE_ENV' => 'production'],
            $site->env_variables ?? [],
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
            'output'  => $output,
            'summary' => $result->successful() ? 'OK' : 'FAILED (exit ' . $result->exitCode() . ')',
        ];
    }

    private function openRepo(Site $site)
    {
        return (new \CzProject\GitPhp\Git())->open($site->repo_path);
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
