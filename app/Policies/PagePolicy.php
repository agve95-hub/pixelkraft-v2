<?php

namespace App\Policies;

use App\Models\Page;
use App\Models\User;

class PagePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return null;
    }

    public function view(User $user, Page $page): bool
    {
        return (string) $page->site?->user_id === (string) $user->id;
    }

    public function update(User $user, Page $page): bool
    {
        return (string) $page->site?->user_id === (string) $user->id;
    }
}
