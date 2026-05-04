<?php

namespace App\Enums;

enum DeployStatus: string
{
    case Draft = 'draft';
    case Queued = 'queued';
    case Cloning = 'cloning';
    case Parsing = 'parsing';
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
            self::Cloning => 'Cloning',
            self::Parsing => 'Parsing',
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

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Queued, self::Idle],
            self::Idle => [self::Queued],
            self::Queued => [self::Cloning, self::Building, self::Failed, self::Idle],
            self::Cloning => [self::Parsing, self::Building, self::Failed],
            self::Parsing => [self::Building, self::Failed],
            self::Building => [self::Deploying, self::Failed],
            self::Deploying => [self::Live, self::Failed, self::Idle],
            // Live → Deploying covers rollbacks; Live → Queued covers re-deploys
            self::Live => [self::Queued, self::Deploying, self::Idle, self::Failed],
            self::Failed => [self::Queued, self::Idle],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }
}
