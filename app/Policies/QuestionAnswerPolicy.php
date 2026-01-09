<?php

namespace App\Policies;

use App\Models\QuestionAnswer;
use App\Models\User;

class QuestionAnswerPolicy
{
    public function update(User $user, QuestionAnswer $questionAnswer): bool
    {
        return $user->id === $questionAnswer->educator_id;
    }

    public function delete(User $user, QuestionAnswer $questionAnswer): bool
    {
        return $user->id === $questionAnswer->educator_id || $user->hasRole('admin');
    }
}
