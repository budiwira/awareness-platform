<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\TrainingModule;
use App\Models\User;
use App\Models\UserModuleAccess;
use App\Services\TenantEntitlement;
use App\Services\UserAccessManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class UserAccessController extends Controller
{
    public function show(Request $request, User $user)
    {
        Gate::authorize('update', $user);

        $tenant = $request->user()->tenant;
        $entitlement = app(TenantEntitlement::class);

        $entitledModuleIds = $entitlement->getEntitledModuleIds($tenant);
        $modules = TrainingModule::whereIn('id', $entitledModuleIds)
            ->orderBy('title')
            ->get(['id', 'title', 'description', 'duration_minutes']);

        $overrides = UserModuleAccess::where('user_id', $user->id)
            ->whereIn('training_module_id', $entitledModuleIds)
            ->pluck('is_allowed', 'training_module_id');

        $moduleAccess = $modules->map(function ($module) use ($overrides) {
            return [
                'id' => $module->id,
                'title' => $module->title,
                'description' => $module->description,
                'duration_minutes' => $module->duration_minutes,
                'is_allowed' => $overrides[$module->id] ?? true,
            ];
        })->values();

        return Inertia::render('Tenant/Users/Access', [
            'user' => $user->only('id', 'name', 'email'),
            'modules' => $moduleAccess,
        ]);
    }

    public function update(Request $request, User $user)
    {
        Gate::authorize('update', $user);

        $tenant = $request->user()->tenant;
        $actor = $request->user();
        $manager = app(UserAccessManager::class);
        $entitlement = app(TenantEntitlement::class);

        $validated = $request->validate([
            'module_ids' => 'required|array',
            'module_ids.*' => 'integer|exists:training_modules,id',
            'is_allowed' => 'required|boolean',
        ]);

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
            'message' => 'Akses modul berhasil diperbarui',
            'user_id' => $user->id,
            'module_ids' => $requestedIds,
            'is_allowed' => $validated['is_allowed'],
        ]);
    }
}