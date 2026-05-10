<?php

namespace App\Services;

use App\Enums\DeployStatus;
use App\Events\DeployFailed;
use App\Models\DeployLog;
use App\Models\DeploymentRelease;
use App\Models\DeploymentTarget;
use App\Models\Site;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Manages the lifecycle of DeployLog, DeploymentRelease, and DeploymentTarget
 * records throughout a deploy or rollback.
 *
 * Extracted from DeployService so that deploy-record concerns (creating, updating,
 * completing, failing releases) are isolated from build and activation concerns.
 */
class ReleaseManager
{
    public function __construct(
        private GitSyncService $git,
        private SiteProvisioningService $provisioning,
    ) {}

    /**
     * Create a DeployLog + DeploymentRelease for a new deploy and transition the
     * site status to Queued.
     *
     * @return array{log: DeployLog, release: DeploymentRelease, target: DeploymentTarget}
     */
    public function begin(Site $site, string $triggeredBy = 'manual', ?string $artifactPath = null): array
    {
        $this->provisioning->initializeSite($site);
        $target = $this->resolveTarget($site, 'production');

        $log = DeployLog::create([
            'site_id' => $site->id,
            'status' => 'queued',
            'triggered_by' => $triggeredBy,
            'created_at' => now(),
        ]);

        $release = $this->startRelease($site, $log, $target, $artifactPath);

        if ($site->deploy_status !== DeployStatus::Queued) {
            $site->transitionDeployStatus(DeployStatus::Queued);
        }
        $log->appendLog('Deploy pipeline queued.');

        return ['log' => $log, 'release' => $release, 'target' => $target];
    }

    public function startRelease(
        Site $site,
        DeployLog $log,
        DeploymentTarget $target,
        ?string $artifactPath = null,
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
            'artifact_path' => $artifactPath,
            'status' => 'building',
            'is_current' => false,
            'meta' => ['triggered_by' => $log->triggered_by],
        ]);
    }

    public function completeRelease(
        Site $site,
        DeploymentRelease $release,
        DeploymentTarget $target,
        ?string $commitSha,
        ?string $artifactPath = null,
        ?string $rollbackOfReleaseId = null,
    ): void {
        $site->deploymentReleases()->where('is_current', true)->update(['is_current' => false]);

        $release->update([
            'source_commit_sha' => $commitSha ?: $release->source_commit_sha,
            'status' => 'active',
            'is_current' => true,
            'activated_at' => now(),
            'artifact_path' => $artifactPath ?: $release->artifact_path ?: $target->deploy_path,
            'meta' => array_merge($release->meta ?? [], [
                'rollback_of_release_id' => $rollbackOfReleaseId,
                'target_environment' => $target->environment,
            ]),
        ]);
    }

    public function fail(
        Site|string $site,
        DeployLog|string|null $log,
        DeploymentRelease|string|null $release,
        Throwable|string $error,
    ): void {
        $site = $site instanceof Site ? $site : Site::query()->findOrFail($site);
        $log = $this->resolveLog($log);
        $release = $this->resolveRelease($release);
        $message = $error instanceof Throwable ? $error->getMessage() : (string) $error;

        if ($log) {
            $log->appendLog("FAILED: {$message}");
            $log->flushLog();
            $log->update([
                'status' => 'failed',
                'duration_ms' => $this->elapsedMs($log),
            ]);
        }

        if ($release) {
            $release->update([
                'status' => 'failed',
                'meta' => array_merge($release->meta ?? [], ['error' => $message]),
            ]);
        }

        $site->update(['deploy_status' => DeployStatus::Failed]);

        Log::error("Deploy failed for [{$site->slug}]", [
            'error' => $message,
            'exception' => $error instanceof Throwable ? $error::class : null,
        ]);

        event(new DeployFailed($site, $log, $message));
    }

    public function pruneSnapshots(Site $site): void
    {
        $maxSnapshots = config('platform.deploy.rollback_snapshots', 10);

        $old = $site->deployLogs()
            ->where('status', 'success')
            ->whereNotNull('snapshot_tag')
            ->orderBy('created_at', 'desc')
            ->skip($maxSnapshots)
            ->take(100)
            ->get();

        foreach ($old as $deploy) {
            if ($deploy->snapshot_tag && $this->git->isCloned($site)) {
                try {
                    $this->git->deleteTag($site, $deploy->snapshot_tag);
                } catch (Throwable $e) {
                    Log::warning("Could not delete snapshot tag [{$deploy->snapshot_tag}] for [{$site->slug}]", [
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $deploy->update(['snapshot_tag' => null]);
        }
    }

    public function runHealthCheck(DeploymentTarget $target, DeployLog $log): void
    {
        if (! $target->health_check_url) {
            $log->appendLog('  No health check URL configured for deployment target.');

            return;
        }

        $scheme = strtolower((string) parse_url($target->health_check_url, PHP_URL_SCHEME));
        if (! in_array($scheme, ['http', 'https'], true)) {
            $log->appendLog('  Health check skipped: URL scheme must be http or https.');

            return;
        }

        $host = (string) parse_url($target->health_check_url, PHP_URL_HOST);
        $ip = null;

        if ($host !== '') {
            $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : null;

            if ($ip === null) {
                $resolved = gethostbyname($host);
                $ip = ($resolved !== $host) ? $resolved : null;
            }

            if ($ip !== null && ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                $log->appendLog('  Health check skipped: URL resolves to a private or reserved IP address.');

                return;
            }
        }

        try {
            $curlResolve = ($host !== '' && $ip !== null)
                ? [CURLOPT_RESOLVE => ["{$host}:80:{$ip}", "{$host}:443:{$ip}"]]
                : [];

            $response = Http::timeout(15)
                ->withOptions(['http_errors' => false, 'curl' => $curlResolve])
                ->get($target->health_check_url);

            $log->appendLog('  Health check: HTTP '.$response->status().' ('.$target->health_check_url.')');

            if ($response->status() >= 500) {
                throw new \RuntimeException("Health check failed with HTTP {$response->status()}");
            }
        } catch (Throwable $e) {
            $log->appendLog('  Health check failed: '.$e->getMessage());
            throw $e;
        }
    }

    public function resolveTarget(Site $site, string $environment): DeploymentTarget
    {
        return $site->deploymentTargets()
            ->where('environment', $environment)
            ->firstOrFail();
    }

    public function elapsedMs(DeployLog $log): int
    {
        return max(0, (int) $log->created_at?->diffInMilliseconds(now()));
    }

    public function resolveLog(DeployLog|string|null $log): ?DeployLog
    {
        if ($log instanceof DeployLog || $log === null) {
            return $log;
        }

        return DeployLog::query()->find($log);
    }

    public function resolveRelease(DeploymentRelease|string|null $release): ?DeploymentRelease
    {
        if ($release instanceof DeploymentRelease || $release === null) {
            return $release;
        }

        return DeploymentRelease::query()->find($release);
    }
}
