<?php

namespace App\Policies;

use App\Models\TrainingModule;
use App\Models\User;

class TrainingModulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role->value === 'super_admin';
    }

    public function create(User $user): bool
    {
        return $user->role->value === 'super_admin';
    }

    public function update(User $user, TrainingModule $trainingModule): bool
    {
        return $user->role->value === 'super_admin';
    }

    public function delete(User $user, TrainingModule $trainingModule): bool
    {
        return $user->role->value === 'super_admin';
    }
}