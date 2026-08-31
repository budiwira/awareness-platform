<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TrainingModule;

class TenantEntitlement
{
    /**
     * Check if tenant has access to a specific feature
     */
    public function hasFeature(Tenant $tenant, string $featureKey): bool
    {
        $subscription = $tenant->currentSubscription();
        
        if (!$subscription || !$subscription->plan) {
            return false;
        }

        $features = $subscription->plan->features ?? [];
        
        return in_array($featureKey, $features, true);
    }

    /**
     * Check if tenant has access to a specific training module
     */
    public function hasModule(Tenant $tenant, int $moduleId): bool
    {
        $subscription = $tenant->currentSubscription();
        
        if (!$subscription || !$subscription->plan) {
            return false;
        }

        $plan = $subscription->plan;

        // If plan includes all modules, check if module is published
        if ($plan->includes_all_modules) {
            $module = TrainingModule::find($moduleId);
            return $module && $module->status === 'published';
        }

        // Otherwise check if module is in the curated list
        return $plan->modules()->where('training_modules.id', $moduleId)->exists();
    }

    /**
     * Get all entitled module IDs for a tenant
     */
    public function getEntitledModuleIds(Tenant $tenant): array
    {
        $subscription = $tenant->currentSubscription();
        
        if (!$subscription || !$subscription->plan) {
            return [];
        }

        $plan = $subscription->plan;

        if ($plan->includes_all_modules) {
            return TrainingModule::where('status', 'published')->pluck('id')->toArray();
        }

        return $plan->modules()->pluck('training_modules.id')->toArray();
    }

    /**
     * Get all entitled features for a tenant
     */
    public function getEntitledFeatures(Tenant $tenant): array
    {
        $subscription = $tenant->currentSubscription();
        
        if (!$subscription || !$subscription->plan) {
            return [];
        }

        return $subscription->plan->features ?? [];
    }
}
