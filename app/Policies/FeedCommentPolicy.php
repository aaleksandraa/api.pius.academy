<?php

namespace App\Policies;

use App\Models\FeedComment;
use App\Models\User;

class FeedCommentPolicy
{
    public function update(User $user, FeedComment $feedComment): bool
    {
        return $user->id === $feedComment->author_id;
    }

    public function delete(User $user, FeedComment $feedComment): bool
    {
        return $user->id === $feedComment->author_id || $user->hasRole('admin');
    }
}
