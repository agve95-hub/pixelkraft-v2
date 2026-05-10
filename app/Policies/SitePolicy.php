<?php

namespace App\Policies;

use App\Models\Site;
use App\Models\User;

class SitePolicy
{
    /**
     * Admins can do anything on any site — skip per-method checks.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return null;
    }

    public function view(User $user, Site $site): bool
    {
        return (string) $site->user_id === (string) $user->id;
    }

    public function update(User $user, Site $site): bool
    {
        return (string) $site->user_id === (string) $user->id;
    }

    public function delete(User $user, Site $site): bool
    {
        return (string) $site->user_id === (string) $user->id;
    }

    /**
     * Only admins may change the build command or build output directory.
     * These fields are executed as shell commands on the VPS server during deploy.
     * Editor-role users (including site owners) cannot modify them.
     */
    public function configureBuild(User $user, Site $site): bool
    {
        return false; // admin before() hook returns true for admins; editors never reach this.
    }
}
