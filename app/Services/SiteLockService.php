<?php

namespace App\Services;

use App\Models\Site;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

class SiteLockService
{
    public function forSite(Site $site, string $resource = 'repo', int $seconds = 120)
    {
        return Cache::lock($this->key($site, $resource), $seconds);
    }

    public function block(Site $site, string $resource, callable $callback, int $lockSeconds = 120, int $waitSeconds = 10): mixed
    {
        $lock = $this->forSite($site, $resource, $lockSeconds);

        try {
            return $lock->block($waitSeconds, $callback);
        } catch (LockTimeoutException $e) {
            throw new \RuntimeException("Timed out waiting for site lock [{$resource}] on [{$site->slug}].", previous: $e);
        }
    }

    private function key(Site $site, string $resource): string
    {
        return "pixelkraft:site:{$site->id}:lock:{$resource}";
    }
}
