<?php

namespace App\Services\Deployment;

use App\Models\DeployLog;
use App\Models\Site;

interface DeploymentAdapter
{
    public function mode(): string;

    public function activationStepLabel(Site $site): string;

    public function artifactDirectory(Site $site): ?string;

    public function supportsAggressiveOptimization(Site $site): bool;

    public function activate(Site $site, DeployLog $log): void;
}
