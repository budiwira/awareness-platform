<?php

namespace App\Policies;

use App\Models\CaseStudy;
use App\Models\User;

class CaseStudyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role->value === 'super_admin';
    }

    public function create(User $user): bool
    {
        return $user->role->value === 'super_admin';
    }

    public function update(User $user, CaseStudy $caseStudy): bool
    {
        return $user->role->value === 'super_admin';
    }

    public function delete(User $user, CaseStudy $caseStudy): bool
    {
        return $user->role->value === 'super_admin';
    }
}
