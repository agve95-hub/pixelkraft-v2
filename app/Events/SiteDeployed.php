<?php

namespace App\Events;

use App\Models\DeployLog;
use App\Models\DeploymentRelease;
use App\Models\Site;

final class SiteDeployed
{
    public function __construct(
        public readonly Site $site,
        public readonly DeployLog $log,
        public readonly DeploymentRelease $release,
    ) {}
}
