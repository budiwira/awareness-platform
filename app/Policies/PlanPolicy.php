<?php

namespace App\Policies;

use App\Models\Plan;
use App\Models\User;

class PlanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role->value === 'super_admin';
    }

    public function create(User $user): bool
    {
        return $user->role->value === 'super_admin';
    }
}