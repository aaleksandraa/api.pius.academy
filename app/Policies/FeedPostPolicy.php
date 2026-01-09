<?php

namespace App\Policies;

use App\Models\FeedPost;
use App\Models\User;

class FeedPostPolicy
{
    public function update(User $user, FeedPost $feedPost): bool
    {
        return $user->id === $feedPost->author_id;
    }

    public function delete(User $user, FeedPost $feedPost): bool
    {
        return $user->id === $feedPost->author_id || $user->hasRole('admin');
    }

    public function pin(User $user, FeedPost $feedPost): bool
    {
        return $user->hasRole('admin');
    }
}
