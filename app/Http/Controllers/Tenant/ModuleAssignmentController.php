<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\ModuleAssignment;
use App\Models\TrainingModule;
use App\Models\User;
use App\Notifications\TrainingAssigned;
use App\Support\Audit\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ModuleAssignmentController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', ModuleAssignment::class);

        $tenantId = $request->user()->tenant_id;

        $assignments = ModuleAssignment::with(['user:id,name,email', 'module:id,title,duration_minutes'])
            ->where('tenant_id', $tenantId)
            ->orderBy('created_at', 'desc')
            ->get();

        $users = User::where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name', 'email']);
        $modules = TrainingModule::where('is_active', true)->orderBy('title')->get(['id', 'title', 'duration_minutes']);

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

        $exists = ModuleAssignment::where('user_id', $targetUser->id)
            ->where('training_module_id', $validated['training_module_id'])
            ->exists();

        if ($exists) {
            return redirect()->back()->withErrors(['user_id' => 'User ini sudah ditugaskan modul tersebut.']);
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

        // NOTIFIKASI: beri tahu user bahwa ia ditugaskan modul baru
        $module = TrainingModule::find($validated['training_module_id']);
        $targetUser->notify(new TrainingAssigned($module));

        return redirect()->route('tenant.assignments.index')->with('success', 'Modul berhasil ditugaskan.');
    }

    public function update(Request $request, ModuleAssignment $assignment)
    {
        Gate::authorize('update', $assignment);

        $validated = $request->validate([
            'status' => ['required', 'in:assigned,in_progress,completed'],
            'score' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        $assignment->update($validated);

        if ($validated['status'] === 'completed') {
            $assignment->completed_at = now();
            $assignment->save();
        }

        Audit::log('assignment.updated', $assignment, ['status' => $validated['status']]);

        return redirect()->route('tenant.assignments.index')->with('success', 'Perubahan disimpan.');
    }
}