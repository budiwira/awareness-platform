<?php

namespace App\Policies;

use App\Models\ModuleAssignment;
use App\Models\User;

class ModuleAssignmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isTenantAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isTenantAdmin();
    }

    public function update(User $actor, ModuleAssignment $assignment): bool
    {
        // Hanya boleh update assignment yang user-nya satu tenant
        return $actor->isTenantAdmin() && $actor->tenant_id === $assignment->user->tenant_id;
    }
}
