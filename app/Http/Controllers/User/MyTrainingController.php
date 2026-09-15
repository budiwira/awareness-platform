<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\ModuleAssignment;
use App\Models\QuizAttempt;
use App\Services\TenantEntitlement;
use App\Services\UserAccessManager;
use App\Support\Audit\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        if (! app(UserAccessManager::class)->hasModuleAccessById(auth()->user(), $assignment->training_module_id)) {
            return Inertia::render('Shared/FeatureLocked', [
                'title' => 'Akses Modul Dibatasi',
                'message' => 'Admin telah membatasi akses Anda ke modul ini.',
                'cta' => 'Hubungi admin organisasi',
            ])->toResponse(request())->setStatusCode(403);
        }

        $assignment->load(['module.pretestQuiz', 'module.posttestQuiz']);
        $module = $assignment->module;

        $pretestAttempt = $module->pretest_quiz_id
            ? QuizAttempt::where('quiz_id', $module->pretest_quiz_id)
                ->where('user_id', auth()->id())
                ->where('status', 'submitted')
                ->latest('submitted_at')
                ->first()
            : null;

        $posttestAttempt = $module->posttest_quiz_id
            ? QuizAttempt::where('quiz_id', $module->posttest_quiz_id)
                ->where('user_id', auth()->id())
                ->whereIn('status', ['submitted', 'expired'])
                ->where('passed', true)
                ->latest('submitted_at')
                ->first()
            : null;

        return Inertia::render('User/MyTraining/Show', [
            'assignment' => $assignment,
            'module' => [
                'id' => $module->id,
                'title' => $module->title,
                'description' => $module->description,
                'content_html' => $module->content_html,
                'duration_minutes' => $module->duration_minutes,
            ],
            'pretestQuiz' => $module->pretestQuiz ? [
                'id' => $module->pretestQuiz->id,
                'title' => $module->pretestQuiz->title,
                'passing_score' => $module->pretestQuiz->passing_score,
            ] : null,
            'posttestQuiz' => $module->posttestQuiz ? [
                'id' => $module->posttestQuiz->id,
                'title' => $module->posttestQuiz->title,
                'passing_score' => $module->posttestQuiz->passing_score,
            ] : null,
            'pretestAttempt' => $pretestAttempt ? [
                'id' => $pretestAttempt->id,
                'score' => $pretestAttempt->score,
                'submitted_at' => $pretestAttempt->submitted_at?->toIso8601String(),
            ] : null,
            'posttestAttempt' => $posttestAttempt ? [
                'id' => $posttestAttempt->id,
                'score' => $posttestAttempt->score,
                'submitted_at' => $posttestAttempt->submitted_at?->toIso8601String(),
            ] : null,
        ]);
    }

    public function markComplete(Request $request, ModuleAssignment $assignment)
    {
        if ($assignment->user_id !== $request->user()->id) {
            abort(403, 'Anda tidak berhak mengubah assignment ini.');
        }

        $user = $request->user();
        abort_unless($user->tenant && app(TenantEntitlement::class)->hasModule($user->tenant, $assignment->training_module_id), 403, 'Organisasi Anda belum mengaktifkan modul ini.');
        abort_unless(app(UserAccessManager::class)->hasModuleAccessById($user, $assignment->training_module_id), 403, 'Akses modul dibatasi oleh admin.');

        $module = $assignment->module;
        abort_if($module->posttest_quiz_id !== null, 403, 'Selesaikan posttest untuk menyelesaikan modul ini.');
        if ($module->pretest_quiz_id) {
            abort_unless(QuizAttempt::where('quiz_id', $module->pretest_quiz_id)
                ->where('user_id', $user->id)
                ->where('status', 'submitted')
                ->exists(), 403, 'Selesaikan pretest terlebih dahulu.');
        }

        if ($assignment->status === 'completed') {
            return redirect()->back();
        }

        DB::transaction(function () use ($assignment) {
            $assignment->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            Audit::log('training.completed', $assignment, [
                'module_id' => $assignment->training_module_id,
            ]);
        });

        return redirect()->route('user.training.index');
    }
}
