<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isTenantAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isTenantAdmin();
    }

    public function update(User $actor, User $target): bool
    {
        // Object-level check: inti pertahanan BOLA/IDOR
        return $actor->isTenantAdmin() && $actor->tenant_id === $target->tenant_id;
    }
}