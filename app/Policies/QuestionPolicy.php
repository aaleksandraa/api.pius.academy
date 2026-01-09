<?php

namespace App\Policies;

use App\Models\Question;
use App\Models\User;

class QuestionPolicy
{
    public function update(User $user, Question $question): bool
    {
        return $user->id === $question->student_id;
    }

    public function delete(User $user, Question $question): bool
    {
        return $user->id === $question->student_id || $user->hasRole('admin');
    }
}
