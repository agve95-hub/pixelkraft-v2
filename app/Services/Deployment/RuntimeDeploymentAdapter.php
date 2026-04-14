<?php

namespace App\Services\Deployment;

use App\Models\DeployLog;
use App\Models\Site;
use App\Services\SiteRuntimeService;

class RuntimeDeploymentAdapter implements DeploymentAdapter
{
    public function __construct(
        private SiteRuntimeService $runtime,
    ) {}

    public function mode(): string
    {
        return SiteRuntimeService::MODE_RUNTIME;
    }

    public function activationStepLabel(Site $site): string
    {
        return 'Starting runtime server...';
    }

    public function artifactDirectory(Site $site): ?string
    {
        return null;
    }

    public function supportsAggressiveOptimization(Site $site): bool
    {
        return false;
    }

    public function activate(Site $site, DeployLog $log): void
    {
        $this->runtime->deploy($site, $log);
        $log->appendLog('  Runtime site deployed on '.$this->runtime->baseUrl($site));
    }
}
