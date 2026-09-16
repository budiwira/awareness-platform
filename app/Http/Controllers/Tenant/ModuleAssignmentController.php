<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\ModuleAssignment;
use App\Models\TrainingModule;
use App\Models\User;
use App\Notifications\TrainingAssigned;
use App\Services\TenantEntitlement;
use App\Support\Audit\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Throwable;

class ModuleAssignmentController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', ModuleAssignment::class);

        $tenantId = $request->user()->tenant_id;
        $tenant = $request->user()->tenant;
        $entitlement = app(TenantEntitlement::class);
        $entitledModuleIds = $entitlement->getEntitledModuleIds($tenant);

        $assignments = ModuleAssignment::with([
            'user' => fn ($query) => $query->withTrashed()->select(['id', 'name', 'email']),
            'module:id,title,duration_minutes',
        ])
            ->where('tenant_id', $tenantId)
            ->orderBy('created_at', 'desc')
            ->get();

        $modules = TrainingModule::whereIn('id', $entitledModuleIds)
            ->where('is_active', true)
            ->where('status', 'published')
            ->orderBy('title')
            ->get(['id', 'title', 'duration_minutes']);
        $assignableModuleIds = $modules->pluck('id');
        $users = User::query()
            ->where('tenant_id', $tenantId)
            ->where('role', UserRole::User->value)
            ->where('is_active', true)
            ->with(['moduleAssignments:id,user_id,training_module_id'])
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(function (User $user) use ($assignableModuleIds) {
                $assignedModuleIds = $user->moduleAssignments->pluck('training_module_id');
                $user->setAttribute(
                    'assignable_module_ids',
                    $assignableModuleIds->diff($assignedModuleIds)->values()->all()
                );
                $user->unsetRelation('moduleAssignments');

                return $user;
            });

        return Inertia::render('Tenant/Assignments/Index', [
            'assignments' => $assignments,
            'users' => $users,
            'modules' => $modules,
        ]);
    }

    public function store(Request $request)
    {
        Gate::authorize('create', ModuleAssignment::class);

        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'training_module_id' => ['required', 'exists:training_modules,id'],
        ]);

        $targetUser = User::where('id', $validated['user_id'])
            ->where('tenant_id', $request->user()->tenant_id)
            ->first();

        if (! $targetUser) {
            abort(403, 'User tidak ditemukan di tenant Anda.');
        }

        if (! $targetUser->isUser() || ! $targetUser->is_active) {
            return redirect()->back()->withErrors([
                'user_id' => 'Anggota harus merupakan learner aktif di organisasi Anda.',
            ]);
        }

        // Validasi entitlement: modul harus ter-entitle
        $tenant = $request->user()->tenant;
        $entitlement = app(TenantEntitlement::class);

        if (! $entitlement->hasModule($tenant, $validated['training_module_id'])) {
            return redirect()->back()->withErrors(['training_module_id' => 'Modul ini tidak termasuk dalam Package organisasi Anda.']);
        }

        $module = TrainingModule::query()
            ->whereKey($validated['training_module_id'])
            ->where('is_active', true)
            ->where('status', 'published')
            ->first();

        if (! $module) {
            return redirect()->back()->withErrors([
                'training_module_id' => 'Modul harus aktif dan sudah dipublikasikan.',
            ]);
        }

        $exists = ModuleAssignment::where('user_id', $targetUser->id)
            ->where('training_module_id', $validated['training_module_id'])
            ->exists();

        if ($exists) {
            return redirect()->back()->withErrors([
                'training_module_id' => 'Modul ini sudah ditugaskan kepada anggota tersebut.',
            ]);
        }

        ModuleAssignment::create([
            'user_id' => $targetUser->id,
            'tenant_id' => $request->user()->tenant_id,
            'training_module_id' => $validated['training_module_id'],
            'status' => 'assigned',
        ]);

        Audit::log('module.assigned', null, [
            'user_id' => $targetUser->id,
            'module_id' => $validated['training_module_id'],
        ]);

        try {
            $targetUser->notify(new TrainingAssigned($module));
        } catch (Throwable $exception) {
            Log::warning('Notifikasi penugasan training gagal dikirim.', [
                'assignment_user_id' => $targetUser->id,
                'training_module_id' => $module->id,
                'exception' => $exception->getMessage(),
            ]);
        }

        return redirect()->route('tenant.assignments.index')->with('success', 'Modul berhasil ditugaskan.');
    }

    public function update(Request $request, ModuleAssignment $assignment)
    {
        Gate::authorize('update', $assignment);

        $validated = $request->validate([
            'status' => ['required', 'in:assigned,in_progress,completed'],
        ]);

        DB::transaction(function () use ($assignment, $validated) {
            $assignment->update(['status' => $validated['status']]);

            if ($validated['status'] === 'completed') {
                $assignment->completed_at = now();
                $assignment->save();
            }

            Audit::log('assignment.updated', $assignment, ['status' => $validated['status']]);
        });

        return redirect()->route('tenant.assignments.index')->with('success', 'Perubahan disimpan.');
    }
}
