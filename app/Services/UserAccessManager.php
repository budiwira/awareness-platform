<?php

namespace App\Services;

use App\Models\TrainingModule;
use App\Models\User;
use App\Models\UserFeatureAccess;
use App\Models\UserModuleAccess;
use App\Support\Audit\Audit;

class UserAccessManager
{
    public function __construct(protected TenantEntitlement $entitlement)
    {
    }

    public function hasModuleAccess(User $user, TrainingModule $module): bool
    {
        if (! $this->entitlement->hasModule($user->tenant, $module->id)) {
            return false;
        }

        $override = UserModuleAccess::where('user_id', $user->id)
            ->where('training_module_id', $module->id)
            ->first();

        return $override?->is_allowed ?? true;
    }

    public function grantModuleAccess(User $user, TrainingModule $module, User $actor): void
    {
        UserModuleAccess::updateOrCreate(
            ['user_id' => $user->id, 'training_module_id' => $module->id],
            [
                'tenant_id' => $user->tenant_id,
                'is_allowed' => true,
                'granted_by' => $actor->id,
                'granted_at' => now(),
            ]
        );

        Audit::log('user.module_access_granted', $module, [
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'granted_by' => $actor->id,
        ]);
    }

    public function revokeModuleAccess(User $user, TrainingModule $module, User $actor): void
    {
        UserModuleAccess::updateOrCreate(
            ['user_id' => $user->id, 'training_module_id' => $module->id],
            [
                'tenant_id' => $user->tenant_id,
                'is_allowed' => false,
                'granted_by' => $actor->id,
                'granted_at' => now(),
            ]
        );

        Audit::log('user.module_access_revoked', $module, [
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'granted_by' => $actor->id,
        ]);
    }

    public function hasFeatureAccess(User $user, string $featureKey): bool
    {
        if (! $this->entitlement->hasFeature($user->tenant, $featureKey)) {
            return false;
        }

        $override = UserFeatureAccess::where('user_id', $user->id)
            ->where('feature_key', $featureKey)
            ->first();

        return $override?->is_allowed ?? true;
    }

    public function grantFeatureAccess(User $user, string $featureKey, User $actor): void
    {
        UserFeatureAccess::updateOrCreate(
            ['user_id' => $user->id, 'feature_key' => $featureKey],
            [
                'tenant_id' => $user->tenant_id,
                'is_allowed' => true,
                'granted_by' => $actor->id,
                'granted_at' => now(),
            ]
        );

        Audit::log('user.feature_access_granted', null, [
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'feature_key' => $featureKey,
            'granted_by' => $actor->id,
        ]);
    }

    public function revokeFeatureAccess(User $user, string $featureKey, User $actor): void
    {
        UserFeatureAccess::updateOrCreate(
            ['user_id' => $user->id, 'feature_key' => $featureKey],
            [
                'tenant_id' => $user->tenant_id,
                'is_allowed' => false,
                'granted_by' => $actor->id,
                'granted_at' => now(),
            ]
        );

        Audit::log('user.feature_access_revoked', null, [
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'feature_key' => $featureKey,
            'granted_by' => $actor->id,
        ]);
    }
}