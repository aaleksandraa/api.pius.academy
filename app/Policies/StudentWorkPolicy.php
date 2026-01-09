<?php

namespace App\Policies;

use App\Models\StudentWork;
use App\Models\User;

class StudentWorkPolicy
{
    public function update(User $user, StudentWork $studentWork): bool
    {
        return $user->id === $studentWork->student_id;
    }

    public function delete(User $user, StudentWork $studentWork): bool
    {
        return $user->id === $studentWork->student_id || $user->hasRole('admin');
    }
}
