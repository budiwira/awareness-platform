<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\TtxSession;
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

    public function assign(User $user, TtxSession $session): bool
    {
        return $this->sameTenant($user, $session)
            && $user->isTenantAdmin()
            && in_array($session->status->value, ['draft', 'ready'], true);
    }

    public function prepare(User $user, TtxSession $session): bool
    {
        return $this->sameTenant($user, $session)
            && $user->isTenantAdmin();
    }

    public function start(User $user, TtxSession $session): bool
    {
        return $this->runtimeActor($user, $session);
    }

    public function advance(User $user, TtxSession $session): bool
    {
        return $this->runtimeActor($user, $session);
    }

    private function sameTenant(User $user, TtxSession $session): bool
    {
        return $user->is_active && $user->tenant_id !== null && $user->tenant_id === $session->tenant_id;
    }

    private function runtimeActor(User $user, TtxSession $session): bool
    {
        return $this->sameTenant($user, $session) && (
            $session->participants()
                ->where('user_id', $user->id)
                ->where('session_role', 'facilitator')
                ->exists()
        );
    }
}
