<?php

namespace App\Services;

use App\Models\Package;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TrainingModule;
use App\Models\User;
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
     * Check if tenant can add more users
     */
    public function canAddUser(Tenant $tenant): bool
    {
        $subscription = $this->resolveSubscription($tenant);

        $package = $subscription?->package ?? Package::where('slug', 'free')->first();

        if (! $package) {
            return true;
        }

        if ($package->max_users === null) {
            return true;
        }

        $activeUserCount = User::where('tenant_id', $tenant->id)->whereNull('deleted_at')->count();

        return $activeUserCount < $package->max_users;
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

        if (! $subscription || ! $subscription->package) {
            return $this->memo[$memoKey] = false;
        }

        $package = $subscription->package;

        // If package includes all modules, check if module is published
        if ($package->includes_all_modules) {
            $module = TrainingModule::find($moduleId);

            return $this->memo[$memoKey] = (bool) ($module && $module->status === 'published');
        }

        // Otherwise check if module is in the curated list
        return $this->memo[$memoKey] = $package->modules()->where('training_modules.id', $moduleId)->exists();
    }

    /**
     * Get all entitled module IDs for a tenant â€” cached 5 menit + memo per request.
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

            if (! $subscription || ! $subscription->package) {
                return [];
            }

            $package = $subscription->package;

            if ($package->includes_all_modules) {
                return TrainingModule::where('status', 'published')->pluck('id')->toArray();
            }

            return $package->modules()->pluck('training_modules.id')->toArray();
        });

        return $this->memo[$memoKey] = $ids;
    }

    /**
     * Get all entitled features for a tenant â€” cached 5 menit + memo per request.
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

            if (! $subscription || ! $subscription->package) {
                return [];
            }

            return $subscription->package->features ?? [];
        });

        return $this->memo[$memoKey] = $features;
    }

    /**
     * Resolve current subscription â€” memo per request untuk hindari N+1.
     */
    private function resolveSubscription(Tenant $tenant): ?Subscription
    {
        $memoKey = "subscription:{$tenant->id}";
        if (array_key_exists($memoKey, $this->memo)) {
            return $this->memo[$memoKey];
        }

        return $this->memo[$memoKey] = $tenant->currentSubscription();
    }
}
