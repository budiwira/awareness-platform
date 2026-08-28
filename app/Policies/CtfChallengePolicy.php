<?php

namespace App\Policies;

use App\Models\CtfChallenge;
use App\Models\User;

class CtfChallengePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role->value === 'super_admin';
    }

    public function create(User $user): bool
    {
        return $user->role->value === 'super_admin';
    }

    public function update(User $user, CtfChallenge $ctfChallenge): bool
    {
        return $user->role->value === 'super_admin';
    }

    public function delete(User $user, CtfChallenge $ctfChallenge): bool
    {
        return $user->role->value === 'super_admin';
    }
}   