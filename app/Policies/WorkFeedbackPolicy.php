<?php

namespace App\Policies;

use App\Models\WorkFeedback;
use App\Models\User;

class WorkFeedbackPolicy
{
    public function update(User $user, WorkFeedback $workFeedback): bool
    {
        return $user->id === $workFeedback->educator_id;
    }

    public function delete(User $user, WorkFeedback $workFeedback): bool
    {
        return $user->id === $workFeedback->educator_id || $user->hasRole('admin');
    }
}
