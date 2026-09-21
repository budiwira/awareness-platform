<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\TtxSession;
use App\Models\TtxSessionParticipant;
use App\Models\User;

class TtxSessionPolicy
{
    public function create(User $user): bool
    {
        return $user->is_active && $user->role === UserRole::TenantAdmin && $user->tenant_id !== null;
    }

    public function view(User $user, TtxSession $session): bool
    {
        return $this->sameTenant($user, $session) && (
            $user->role === UserRole::TenantAdmin
            || $session->participants()->where('user_id', $user->id)->exists()
        );
    }

    public function manage(User $user, TtxSession $session): bool
    {
        return $this->sameTenant($user, $session) && (
            $user->role === UserRole::TenantAdmin
            || $session->participants()
                ->where('user_id', $user->id)
                ->where('session_role', 'facilitator')
                ->exists()
        );
    }

    public function assign(User $user, TtxSession $session): bool
    {
        return $this->manage($user, $session);
    }

    private function sameTenant(User $user, TtxSession $session): bool
    {
        return $user->is_active && $user->tenant_id !== null && $user->tenant_id === $session->tenant_id;
    }
}
