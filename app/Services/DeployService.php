<?php

namespace App\Services;

use App\Enums\DeployStatus;
use App\Events\SiteDeployed;
use App\Models\DeployLog;
use App\Models\DeploymentRelease;
use App\Models\DeploymentTarget;
use App\Models\Site;
use App\Services\Deployment\DeploymentAdapter;
use App\Services\Deployment\RuntimeDeploymentAdapter;
use App\Services\Deployment\StaticDeploymentAdapter;
use Illuminate\Support\Facades\Log;
use Throwable;

class DeployService
{
    public function __construct(
        private GitSyncService $git,
        private NginxConfigService $nginx,
        private SiteRuntimeService $runtime,
        private SiteProvisioningService $provisioning,
        private StaticDeploymentAdapter $staticDeployments,
        private RuntimeDeploymentAdapter $runtimeDeployments,
        private BuildService $builder,
        private AssetOptimisationService $assets,
        private ReleaseManager $releases,
    ) {}

    public function deploy(Site $site, string $triggeredBy = 'manual'): DeployLog
    {
        ['log' => $log, 'release' => $release] = $this->beginDeployment($site, $triggeredBy);

        try {
            $this->provisionEnvironment($site, $log, $release);
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
        $artifactPath = $this->adapterFor($site)->artifactDirectory($site);

        return $this->releases->begin($site, $triggeredBy, $artifactPath);
    }

    public function provisionEnvironment(Site $site, DeployLog $log, DeploymentRelease $release): void
    {
        $site->transitionDeployStatus(DeployStatus::Building);

        $target = $release->deploymentTarget()->first() ?: $this->releases->resolveTarget($site, 'production');

        $log->appendLog('[1/4] Provisioning site environment...');
        $this->provisioning->initializeSite($site);

        File::ensureDirectoryExists(dirname((string) $site->deploy_path), 0755, true);

        if ($target->deploy_path) {
            File::ensureDirectoryExists(dirname((string) $target->deploy_path), 0755, true);
        }

        $log->flushLog();
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

        $log->appendLog('  Deployment target: '.$target->environment.' ('.($target->runtime_type ?: 'static').')');
        $log->appendLog('  Release strategy: '.($target->release_strategy ?: 'symlink'));
        $log->appendLog('  Repo path: '.($site->repo_path ?: 'n/a'));
        $log->appendLog('  Deploy path: '.($target->deploy_path ?: $site->deploy_path ?: 'n/a'));
    }

    public function buildSite(Site $site, DeployLog $log, DeploymentRelease $release): void
    {
        $log->appendLog('[2/4] Pulling, installing dependencies, and building...');

        $this->pullLatest($site, $log);
        $this->installDependencies($site, $log);
        $this->runBuild($site, $log);
        $this->optimizeAssets($site, $log);

        $log->flushLog();
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

        $log->flushLog();
        $release->update([
            'status' => 'tracked',
            'meta' => array_merge($release->meta ?? [], [
                'tracking_injected_at' => now()->toIso8601String(),
            ]),
        ]);
    }

    public function activateRelease(Site $site, DeployLog $log, DeploymentRelease $release): void
    {
        $site->transitionDeployStatus(DeployStatus::Deploying);

        $target = $release->deploymentTarget()->first() ?: $this->releases->resolveTarget($site, 'production');
        $adapter = $this->adapterFor($site);

        $log->appendLog('[4/4] '.$adapter->activationStepLabel($site));
        $adapter->activate($site, $log);

        if ($this->shouldReloadNginx($site)) {
            $log->appendLog('  Reloading Nginx...');
            $this->nginx->reloadNginx();
        } else {
            $log->appendLog('  Skipping Nginx reload (no site config generated).');
        }

        $isCloned = $this->git->isCloned($site);
        $sha = $isCloned ? $this->git->currentCommitSha($site) : null;

        $log->update([
            'status' => 'success',
            'commit_sha' => $sha,
            'duration_ms' => $this->releases->elapsedMs($log),
            'snapshot_tag' => 'deploy-'.now()->format('Ymd-His'),
        ]);

        $site->update([
            'deploy_status' => DeployStatus::Live,
            'last_deployed_at' => now(),
        ]);

        $this->releases->completeRelease($site, $release, $target, $sha, $adapter->artifactDirectory($site));
        $this->releases->runHealthCheck($target, $log);

        if ($sha && $isCloned) {
            try {
                $this->git->createTag($site, $log->snapshot_tag);
            } catch (Throwable $e) {
                Log::warning("Failed to create rollback tag for [{$site->slug}]", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->releases->pruneSnapshots($site);
        $log->appendLog("Deploy successful in {$log->durationFormatted()}");
        $log->flushLog();

        event(new SiteDeployed($site, $log, $release));
    }

    public function markDeploymentFailed(
        Site|string $site,
        DeployLog|string|null $log,
        DeploymentRelease|string|null $release,
        Throwable|string $error,
    ): void {
        $this->releases->fail($site, $log, $release, $error);
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
            $site->transitionDeployStatus(DeployStatus::Deploying);
            $target = $this->releases->resolveTarget($site, 'production');
            $rollbackSourceReleaseId = $site->deploymentReleases()
                ->where('source_commit_sha', $targetDeploy->commit_sha)
                ->latest('activated_at')
                ->value('id');
            $release = $this->releases->startRelease($site, $log, $target, null, $targetDeploy->commit_sha, $rollbackSourceReleaseId);

            $log->appendLog("Rolling back to {$targetDeploy->snapshot_tag}...");

            $this->git->checkout($site, $targetDeploy->snapshot_tag);

            // Rebuild from the snapshot source so the deployed artifacts actually
            // match the rollback target.  git checkout restores source files only;
            // build output directories are in .gitignore and are not restored.
            $log->appendLog('  Rebuilding from snapshot source...');
            $this->installDependencies($site->fresh(), $log);
            $this->runBuild($site->fresh(), $log);
            $this->optimizeAssets($site->fresh(), $log);

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
                'duration_ms' => $this->releases->elapsedMs($log),
                'snapshot_tag' => $targetDeploy->snapshot_tag,
            ]);

            $site->update([
                'deploy_status' => DeployStatus::Live,
                'last_deployed_at' => now(),
            ]);
            $this->releases->completeRelease($site, $release, $target, $targetDeploy->commit_sha, null, $release->rollback_of_release_id);
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
        $this->builder->installDependencies($site, $log);
    }

    private function runBuild(Site $site, DeployLog $log): void
    {
        $this->builder->runBuild($site, $log);
    }

    private function optimizeAssets(Site $site, DeployLog $log): void
    {
        $this->assets->optimizeAssets($site, $log, $this->adapterFor($site));
    }

    private function injectTracking(Site $site, DeployLog $log, DeploymentRelease $release): void
    {
        $this->assets->injectTracking($site, $log, $release, $this->adapterFor($site));
    }

    private function shouldReloadNginx(Site $site): bool
    {
        return ! empty($site->nginx_conf_path) && File::exists($site->nginx_conf_path);
    }

    private function adapterFor(Site $site): DeploymentAdapter
    {
        return match ($this->runtime->deploymentMode($site)) {
            SiteRuntimeService::MODE_RUNTIME => $this->runtimeDeployments,
            default => $this->staticDeployments,
        };
    }

    /**
     * Pre/post deploy hooks (pre_deploy_hook, post_deploy_hook) were removed from
     * the schema — see migration 2026_05_10_000001_drop_deploy_hook_columns.
     * Implementing them requires satisfying the 7-point security contract in
     * BuildService::validateBuildCommand() before any shell execution is wired up.
     */
}
