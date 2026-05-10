<?php

namespace App\Events;

use App\Models\Site;

final class SiteSynced
{
    public function __construct(
        public readonly Site $site,
        public readonly bool $hasChanges,
        public readonly string $triggeredBy = 'webhook',
    ) {}
}
