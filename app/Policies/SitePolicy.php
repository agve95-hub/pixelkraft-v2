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
}
