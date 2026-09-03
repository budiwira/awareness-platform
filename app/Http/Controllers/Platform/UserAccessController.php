<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TrainingModule;
use App\Models\User;
use App\Models\UserModuleAccess;
use App\Services\TenantEntitlement;
use App\Services\UserAccessManager;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Models\UserFeatureAccess;
use Inertia\Inertia;

class UserAccessController extends Controller
{
    public function show(Request $request, Tenant $tenant)
    {
        $entitlement = app(TenantEntitlement::class);
        $entitledModuleIds = $entitlement->getEntitledModuleIds($tenant);
        $entitledFeatures = $entitlement->getEntitledFeatures($tenant);
        $modules = TrainingModule::whereIn('id', $entitledModuleIds)
            ->orderBy('title')
            ->get(['id', 'title']);

        $users = User::where('tenant_id', $tenant->id)->orderBy('name')->get(['id', 'name', 'email', 'role']);

        $allAccess = $users->map(function ($user) use ($modules, $entitledFeatures) {
            $moduleOverrides = UserModuleAccess::where('user_id', $user->id)
                ->whereIn('training_module_id', $modules->pluck('id'))
                ->pluck('is_allowed', 'training_module_id');

            $featureOverrides = UserFeatureAccess::where('user_id', $user->id)
                ->whereIn('feature_key', $entitledFeatures)
                ->pluck('is_allowed', 'feature_key');

            return [
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value ?? $user->role,
                'modules' => $modules->map(fn($m) => [
                    'module_id' => $m->id,
                    'title' => $m->title,
                    'is_allowed' => $moduleOverrides[$m->id] ?? true,
                ])->values(),
                'features' => collect($entitledFeatures)->map(fn($key) => [
                    'key' => $key,
                    'is_allowed' => $featureOverrides[$key] ?? true,
                ])->values(),
            ];
        });

        return Inertia::render('Platform/Tenants/UserAccess', [
            'tenant' => ['id' => $tenant->id, 'name' => $tenant->name],
            'users' => $allAccess,
        ]);
    }

    public function update(Request $request, Tenant $tenant)
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'module_ids' => 'nullable|array',
            'module_ids.*' => 'integer|exists:training_modules,id',
            'feature_keys' => 'nullable|array',
            'feature_keys.*' => 'string',
            'is_allowed' => 'required|boolean',
        ]);

        $requestedModuleIds = $validated['module_ids'] ?? [];
        $requestedFeatureKeys = $validated['feature_keys'] ?? [];

        if (empty($requestedModuleIds) && empty($requestedFeatureKeys)) {
            throw ValidationException::withMessages([
                'access' => 'Pilih minimal satu modul atau fitur.',
            ]);
        }

        $user = User::where('id', $validated['user_id'])
            ->where('tenant_id', $tenant->id)
            ->firstOrFail();

        $actor = $request->user();
        $manager = app(UserAccessManager::class);
        $entitlement = app(TenantEntitlement::class);

        // Validate modules are entitled by tenant package
        $entitledModuleIds = $entitlement->getEntitledModuleIds($tenant);
        $invalidModuleIds = array_values(array_diff($requestedModuleIds, $entitledModuleIds));

        if (!empty($invalidModuleIds)) {
            return response()->json([
                'error' => 'Modul tidak termasuk dalam paket tenant',
                'invalid_module_ids' => $invalidModuleIds,
            ], 422);
        }

        // Validate features are entitled by tenant package
        $entitledFeatures = $entitlement->getEntitledFeatures($tenant);
        $invalidFeatureKeys = array_values(array_diff($requestedFeatureKeys, $entitledFeatures));

        if (!empty($invalidFeatureKeys)) {
            return response()->json([
                'error' => 'Fitur tidak termasuk dalam paket tenant',
                'invalid_feature_keys' => $invalidFeatureKeys,
            ], 422);
        }

        $modules = TrainingModule::whereIn('id', $requestedModuleIds)->get();

        foreach ($modules as $module) {
            if ($validated['is_allowed']) {
                $manager->grantModuleAccess($user, $module, $actor);
            } else {
                $manager->revokeModuleAccess($user, $module, $actor);
            }
        }

        foreach ($requestedFeatureKeys as $featureKey) {
            if ($validated['is_allowed']) {
                $manager->grantFeatureAccess($user, $featureKey, $actor);
            } else {
                $manager->revokeFeatureAccess($user, $featureKey, $actor);
            }
        }

        return response()->json([
            'message' => 'Akses user berhasil diperbarui (super admin override)',
            'user_id' => $user->id,
            'module_ids' => $requestedModuleIds,
            'feature_keys' => $requestedFeatureKeys,
            'is_allowed' => $validated['is_allowed'],
            'actor_id' => $actor->id,
        ]);
    }
}