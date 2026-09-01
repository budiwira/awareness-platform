<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TrainingModule;
use Illuminate\Support\Facades\Cache;

class TenantEntitlement
{
    /**
     * Request-scoped memoization untuk hindari query berulang dalam satu request.
     * Cache persistent (store array/database) ditangani via Cache::remember di getter.
     *
     * @var array<string, mixed>
     */
    private array $memo = [];

    /**
     * Check if tenant has access to a specific feature
     */
    public function hasFeature(Tenant $tenant, string $featureKey): bool
    {
        $features = $this->getEntitledFeatures($tenant);

        return in_array($featureKey, $features, true);
    }

    /**
     * Check if tenant has access to a specific training module
     */
    public function hasModule(Tenant $tenant, int $moduleId): bool
    {
        $memoKey = "hasModule:{$tenant->id}:{$moduleId}";
        if (array_key_exists($memoKey, $this->memo)) {
            return $this->memo[$memoKey];
        }

        $subscription = $this->resolveSubscription($tenant);

        if (!$subscription || !$subscription->plan) {
            return $this->memo[$memoKey] = false;
        }

        $plan = $subscription->plan;

        // If plan includes all modules, check if module is published
        if ($plan->includes_all_modules) {
            $module = TrainingModule::find($moduleId);

            return $this->memo[$memoKey] = (bool) ($module && $module->status === 'published');
        }

        // Otherwise check if module is in the curated list
        return $this->memo[$memoKey] = $plan->modules()->where('training_modules.id', $moduleId)->exists();
    }

    /**
     * Get all entitled module IDs for a tenant — cached 5 menit + memo per request.
     *
     * @return array<int, int>
     */
    public function getEntitledModuleIds(Tenant $tenant): array
    {
        $memoKey = "moduleIds:{$tenant->id}";
        if (array_key_exists($memoKey, $this->memo)) {
            return $this->memo[$memoKey];
        }

        $cacheKey = "entitlement:module_ids:{$tenant->id}";

        $ids = Cache::remember($cacheKey, 300, function () use ($tenant) {
            $subscription = $this->resolveSubscription($tenant);

            if (!$subscription || !$subscription->plan) {
                return [];
            }

            $plan = $subscription->plan;

            if ($plan->includes_all_modules) {
                return TrainingModule::where('status', 'published')->pluck('id')->toArray();
            }

            return $plan->modules()->pluck('training_modules.id')->toArray();
        });

        return $this->memo[$memoKey] = $ids;
    }

    /**
     * Get all entitled features for a tenant — cached 5 menit + memo per request.
     *
     * @return array<int, string>
     */
    public function getEntitledFeatures(Tenant $tenant): array
    {
        $memoKey = "features:{$tenant->id}";
        if (array_key_exists($memoKey, $this->memo)) {
            return $this->memo[$memoKey];
        }

        $cacheKey = "entitlement:features:{$tenant->id}";

        $features = Cache::remember($cacheKey, 300, function () use ($tenant) {
            $subscription = $this->resolveSubscription($tenant);

            if (!$subscription || !$subscription->plan) {
                return [];
            }

            return $subscription->plan->features ?? [];
        });

        return $this->memo[$memoKey] = $features;
    }

    /**
     * Resolve current subscription — memo per request untuk hindari N+1.
     */
    private function resolveSubscription(Tenant $tenant): ?\App\Models\Subscription
    {
        $memoKey = "subscription:{$tenant->id}";
        if (array_key_exists($memoKey, $this->memo)) {
            return $this->memo[$memoKey];
        }

        return $this->memo[$memoKey] = $tenant->currentSubscription();
    }
}
