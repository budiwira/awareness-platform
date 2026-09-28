<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\TtxSession;
use App\Models\User;

class TtxSessionPolicy
{
    /**
     * Collection-level authorization for the Tenant Admin sessions index.
     * Active, Tenant Admin, non-null tenant only.
     */
    public function viewAny(User $user): bool
    {
        return $user->is_active
            && $user->isTenantAdmin()
            && $user->tenant_id !== null;
    }

    public function create(User $user): bool
    {
        return $user->is_active && $user->role === UserRole::TenantAdmin && $user->tenant_id !== null;
    }

    public function view(User $user, TtxSession $session): bool
    {
        return $this->runtimeActor($user, $session) || $this->participate($user, $session);
    }

    public function assign(User $user, TtxSession $session): bool
    {
        return $this->sameTenant($user, $session)
            && $user->isTenantAdmin()
            && $session->created_by === $user->id
            && $session->status->value === 'draft';
    }

    public function prepare(User $user, TtxSession $session): bool
    {
        return $this->sameTenant($user, $session)
            && $user->isTenantAdmin()
            && $session->created_by === $user->id;
    }

    public function start(User $user, TtxSession $session): bool
    {
        return $this->runtimeActor($user, $session);
    }

    public function advance(User $user, TtxSession $session): bool
    {
        return $this->runtimeActor($user, $session);
    }

    public function facilitate(User $user, TtxSession $session): bool
    {
        return $this->runtimeActor($user, $session);
    }

    public function debrief(User $user, TtxSession $session): bool
    {
        return $this->runtimeActor($user, $session);
    }

    public function participate(User $user, TtxSession $session): bool
    {
        return $this->sameTenant($user, $session)
            && $user->role === UserRole::User
            && $session->created_by !== $user->id
            && $session->participants()
                ->where('tenant_id', $session->tenant_id)
                ->where('user_id', $user->id)
                ->whereNotNull('team_id')
                ->whereHas('team', fn ($query) => $query
                    ->where('tenant_id', $session->tenant_id)
                    ->where('session_id', $session->id))
                ->exists();
    }

    public function respond(User $user, TtxSession $session): bool
    {
        return $this->sameTenant($user, $session)
            && $user->role === UserRole::User
            && $session->participants()
                ->where('tenant_id', $session->tenant_id)
                ->where('user_id', $user->id)
                ->whereNotNull('team_id')
                ->whereHas('team', fn ($query) => $query
                    ->where('tenant_id', $session->tenant_id)
                    ->where('session_id', $session->id))
                ->exists();
    }

    private function sameTenant(User $user, TtxSession $session): bool
    {
        return $user->is_active && $user->tenant_id !== null && $user->tenant_id === $session->tenant_id;
    }

    private function runtimeActor(User $user, TtxSession $session): bool
    {
        return $this->sameTenant($user, $session)
            && $user->isTenantAdmin()
            && $session->created_by === $user->id;
    }
}
