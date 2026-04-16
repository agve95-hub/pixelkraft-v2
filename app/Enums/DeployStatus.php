<?php

namespace App\Enums;

enum DeployStatus: string
{
    case Draft = 'draft';
    case Queued = 'queued';
    case Building = 'building';
    case Deploying = 'deploying';
    case Live = 'live';
    case Failed = 'failed';
    case Idle = 'idle';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Queued => 'Queued',
            self::Building => 'Building',
            self::Deploying => 'Deploying',
            self::Live => 'Live',
            self::Failed => 'Failed',
            self::Idle => 'Idle',
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [self::Building, self::Deploying, self::Queued], true);
    }
}
