<?php

namespace App\Events;

use App\Models\DeployLog;
use App\Models\Site;

final class DeployFailed
{
    public function __construct(
        public readonly Site $site,
        public readonly ?DeployLog $log,
        public readonly string $error,
    ) {}
}
