<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\ModuleAssignment;
use App\Services\TenantEntitlement;
use App\Services\UserAccessManager;
use App\Support\Audit\Audit;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MyTrainingController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->id;
        $tenant = $request->user()->tenant;

        if (! $tenant) {
            abort(403, 'Anda tidak terikat pada organisasi.');
        }

        $entitlement = app(TenantEntitlement::class);
        $entitledModuleIds = $entitlement->getEntitledModuleIds($tenant);

        // User hanya bisa melihat assignment miliknya sendiri,
        // termasuk info apakah modulnya punya quiz aktif
        $assignments = ModuleAssignment::with([
            'module:id,title,description,duration_minutes',
            'module.quiz:id,training_module_id',
        ])
            ->where('user_id', $userId)
            ->whereIn('training_module_id', $entitledModuleIds)
            ->orderBy('created_at', 'desc')
            ->get();

        // Filter out modules revoked from this user
        $manager = app(UserAccessManager::class);
        $assignments = $assignments->filter(function ($assignment) use ($manager, $request) {
            return $manager->hasModuleAccessById($request->user(), $assignment->training_module_id);
        })->values();

        return Inertia::render('User/MyTraining/Index', [
            'assignments' => $assignments,
        ]);
    }

    public function show(ModuleAssignment $assignment)
    {
        if ($assignment->user_id !== auth()->id()) {
            abort(403, 'Anda tidak berhak melihat assignment ini.');
        }

        $tenant = auth()->user()->tenant;
        $entitlement = app(TenantEntitlement::class);

        if (! $tenant || ! $entitlement->hasModule($tenant, $assignment->training_module_id)) {
            return Inertia::render('Shared/FeatureLocked', [
                'title' => 'Modul Tidak Tersedia',
                'message' => 'Organisasi Anda belum mengaktifkan modul ini.',
                'cta' => 'Hubungi admin organisasi',
            ])->toResponse(request())->setStatusCode(403);
        }

        // Per-user access check: revoked user blocked
        if (! app(UserAccessManager::class)->hasModuleAccessById(auth()->user(), $assignment->training_module_id)) {
            return Inertia::render('Shared/FeatureLocked', [
                'title' => 'Akses Modul Dibatasi',
                'message' => 'Admin telah membatasi akses Anda ke modul ini.',
                'cta' => 'Hubungi admin organisasi',
            ])->toResponse(request())->setStatusCode(403);
        }

        $assignment->load(['module:id,title,description,content,duration_minutes']);

        return Inertia::render('User/MyTraining/Show', [
            'assignment' => $assignment,
        ]);
    }

    public function markComplete(Request $request, ModuleAssignment $assignment)
    {
        if ($assignment->user_id !== $request->user()->id) {
            abort(403, 'Anda tidak berhak mengubah assignment ini.');
        }

        if ($assignment->status === 'completed') {
            return redirect()->back();
        }

        $assignment->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        Audit::log('training.completed', $assignment, [
            'module_id' => $assignment->training_module_id,
        ]);

        return redirect()->route('user.training.index');
    }
}
