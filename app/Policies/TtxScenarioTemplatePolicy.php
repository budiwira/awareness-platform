<?php

namespace App\Policies;

use App\Models\TtxScenarioTemplate;
use App\Models\User;

class TtxScenarioTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->isSuperAdmin();
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, TtxScenarioTemplate $template): bool
    {
        return $this->viewAny($user);
    }
}
