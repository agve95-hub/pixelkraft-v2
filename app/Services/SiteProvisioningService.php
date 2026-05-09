<?php

namespace App\Services;

use App\Models\DeploymentTarget;
use App\Models\Site;
use App\Models\TrackingInstallation;
use Illuminate\Support\Facades\File;

class SiteProvisioningService
{
    public function initializeSite(Site $site): void
    {
        File::ensureDirectoryExists(dirname((string) $site->repo_path), 0755, true);
        File::ensureDirectoryExists((string) $site->repo_path, 0755, true);
        File::ensureDirectoryExists(dirname((string) $site->deploy_path), 0755, true);
        if ($site->deploy_path) {
            File::ensureDirectoryExists(rtrim((string) $site->deploy_path, '/\\').'/releases', 0755, true);
        }

        $this->ensureDefaultDeploymentTargets($site);
        $this->ensureDefaultTrackingInstallation($site);
    }

    public function ensureDefaultDeploymentTargets(Site $site): void
    {
        $healthUrl = $site->domain ? 'https://'.$site->domain.'/' : null;
        $runtimeType = app(SiteRuntimeService::class)->deploymentMode($site);

        DeploymentTarget::query()->firstOrCreate(
            [
                'site_id' => $site->id,
                'environment' => 'production',
            ],
            [
                'host' => $site->ssh_host ?: $site->domain,
                'deploy_path' => $site->deploy_path,
                'runtime_type' => $runtimeType,
                'health_check_url' => $healthUrl,
                'release_strategy' => 'symlink',
                'is_active' => true,
            ],
        );

        DeploymentTarget::query()->firstOrCreate(
            [
                'site_id' => $site->id,
                'environment' => 'staging',
            ],
            [
                'host' => $site->ssh_host ?: $site->domain,
                'deploy_path' => $site->deploy_path ? rtrim($site->deploy_path, '/\\').'-staging' : null,
                'runtime_type' => $runtimeType,
                'health_check_url' => null,
                'release_strategy' => 'symlink',
                'is_active' => false,
            ],
        );
    }

    public function ensureDefaultTrackingInstallation(Site $site): TrackingInstallation
    {
        return TrackingInstallation::query()->firstOrCreate(
            [
                'site_id' => $site->id,
                'provider' => 'platform',
            ],
            [
                'measurement_id' => $site->ga_property_id,
                'container_id' => $site->gtm_id,
                'script_route' => route('tracking.script', ['site' => $site]),
                'collector_path' => route('tracking.collect', ['site' => $site]),
                'consent_mode' => false,
                'is_active' => true,
            ],
        );
    }
}
