<?php

namespace App\Policies;

use App\Models\Quiz;
use App\Models\User;

class QuizPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role->value === 'super_admin';
    }

    public function create(User $user): bool
    {
        return $user->role->value === 'super_admin';
    }

    public function update(User $user, Quiz $quiz): bool
    {
        return $user->role->value === 'super_admin';
    }

    public function delete(User $user, Quiz $quiz): bool
    {
        return $user->role->value === 'super_admin';
    }
}