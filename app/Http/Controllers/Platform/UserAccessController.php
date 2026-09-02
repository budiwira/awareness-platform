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

class UserAccessController extends Controller
{
    public function show(Request $request, Tenant $tenant)
    {
        $entitlement = app(TenantEntitlement::class);
        $entitledModuleIds = $entitlement->getEntitledModuleIds($tenant);
        $modules = TrainingModule::whereIn('id', $entitledModuleIds)->get();

        $users = User::where('tenant_id', $tenant->id)->get();

        $allAccess = $users->map(function ($user) use ($modules) {
            $overrides = UserModuleAccess::where('user_id', $user->id)
                ->whereIn('training_module_id', $modules->pluck('id'))
                ->pluck('is_allowed', 'training_module_id');

            $moduleAccess = $modules->map(function ($module) use ($overrides) {
                return [
                    'module_id' => $module->id,
                    'title' => $module->title,
                    'is_allowed' => $overrides[$module->id] ?? true,
                ];
            });

            return [
                'user_id' => $user->id,
                'name' => $user->name,
                'modules' => $moduleAccess,
            ];
        });

        return response()->json([
            'tenant_id' => $tenant->id,
            'users' => $allAccess,
        ]);
    }

    public function update(Request $request, Tenant $tenant)
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'module_ids' => 'required|array',
            'module_ids.*' => 'integer|exists:training_modules,id',
            'is_allowed' => 'required|boolean',
        ]);

        $user = User::where('id', $validated['user_id'])
            ->where('tenant_id', $tenant->id)
            ->firstOrFail();

        $actor = $request->user();
        $manager = app(UserAccessManager::class);
        $entitlement = app(TenantEntitlement::class);

        $entitledModuleIds = $entitlement->getEntitledModuleIds($tenant);
        $requestedIds = $validated['module_ids'];
        $invalidIds = array_diff($requestedIds, $entitledModuleIds);

        if (!empty($invalidIds)) {
            return response()->json([
                'error' => 'Modul tidak termasuk dalam paket tenant',
                'invalid_module_ids' => $invalidIds,
            ], 422);
        }

        $modules = TrainingModule::whereIn('id', $requestedIds)->get();

        foreach ($modules as $module) {
            if ($validated['is_allowed']) {
                $manager->grantModuleAccess($user, $module, $actor);
            } else {
                $manager->revokeModuleAccess($user, $module, $actor);
            }
        }

        return response()->json([
            'message' => 'Akses modul berhasil diperbarui (super admin override)',
            'user_id' => $user->id,
            'module_ids' => $requestedIds,
            'is_allowed' => $validated['is_allowed'],
            'actor_id' => $actor->id,
        ]);
    }
}