<?php

namespace App\Policies;

use App\Models\BlogPost;
use App\Models\User;

class BlogPostPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return null;
    }

    public function view(User $user, BlogPost $blogPost): bool
    {
        return (string) $blogPost->site?->user_id === (string) $user->id;
    }

    public function update(User $user, BlogPost $blogPost): bool
    {
        return (string) $blogPost->site?->user_id === (string) $user->id;
    }

    public function delete(User $user, BlogPost $blogPost): bool
    {
        return (string) $blogPost->site?->user_id === (string) $user->id;
    }
}
