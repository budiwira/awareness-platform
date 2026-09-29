<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\ModuleAssignment;
use App\Services\AssessmentLifecycle;
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
        if (! auth()->user()->isUser()
            || $assignment->user_id !== auth()->id()
            || $assignment->tenant_id !== auth()->user()->tenant_id) {
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

        $assignment->load(['module', 'pretestQuiz', 'posttestQuiz']);
        $module = $assignment->module;
        $lifecycle = app(AssessmentLifecycle::class)->state($assignment);
        $pretestAttempt = $lifecycle['pretest_attempt'];
        $posttestAttempt = $lifecycle['posttest_attempt'];
        $pretestQuiz = $lifecycle['pretest_quiz'];
        $posttestQuiz = $lifecycle['posttest_quiz'];

        $snapshot = $assignment->module_snapshot ?? $module->runtimeSnapshot();
        $contentAvailable = $pretestQuiz === null || $pretestAttempt !== null;

        return Inertia::render('User/MyTraining/Show', [
            'assignment' => [
                'id' => $assignment->id,
                'status' => $assignment->status,
            ],
            'module' => [
                'id' => $snapshot['module_id'],
                'title' => $snapshot['title'],
                'description' => $snapshot['description'],
                'content_html' => $contentAvailable ? $snapshot['content_html'] : null,
                'duration_minutes' => $snapshot['duration_minutes'],
            ],
            'lifecycle' => collect($lifecycle)->except(['pretest_attempt', 'posttest_attempt', 'pretest_quiz', 'posttest_quiz'])->all(),
            'pretestQuiz' => $pretestQuiz ? [
                'id' => $pretestQuiz->id,
                'title' => $pretestQuiz->title,
                'passing_score' => $pretestQuiz->passing_score,
            ] : null,
            'posttestQuiz' => $posttestQuiz ? [
                'id' => $posttestQuiz->id,
                'title' => $posttestQuiz->title,
                'passing_score' => $posttestQuiz->passing_score,
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

    public function startContent(Request $request, ModuleAssignment $assignment)
    {
        $this->ensureLifecycleAccess($request, $assignment);

        DB::transaction(function () use ($assignment) {
            $locked = ModuleAssignment::whereKey($assignment->id)->lockForUpdate()->firstOrFail();
            $this->ensureContentAvailable($locked);

            $locked->started_at ??= now();
            $locked->content_started_at ??= now();
            if ($locked->status === 'assigned') {
                $locked->status = 'in_progress';
            }
            $locked->save();
            Audit::log('training.content_started', $locked, ['module_id' => $locked->training_module_id]);
        });

        return back();
    }

    public function completeContent(Request $request, ModuleAssignment $assignment)
    {
        $this->ensureLifecycleAccess($request, $assignment, allowCompleted: true);

        DB::transaction(function () use ($assignment) {
            $locked = ModuleAssignment::whereKey($assignment->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === 'completed' && $locked->content_completed_at !== null) {
                return;
            }
            $this->ensureContentAvailable($locked);

            $locked->started_at ??= now();
            $locked->content_started_at ??= now();
            $locked->content_completed_at ??= now();

            if (app(AssessmentLifecycle::class)->quiz($locked, 'posttest')) {
                $locked->status = 'in_progress';
            } else {
                $locked->status = 'completed';
                $locked->completed_at ??= now();
            }
            $locked->save();

            Audit::log('training.content_completed', $locked, [
                'module_id' => $locked->training_module_id,
                'assignment_completed' => $locked->status === 'completed',
            ]);
        });

        return back();
    }

    private function ensureLifecycleAccess(Request $request, ModuleAssignment $assignment, bool $allowCompleted = false): void
    {
        $user = $request->user();
        abort_unless($user->isUser(), 403, 'Hanya learner yang dapat mengubah progres materi.');
        abort_unless($assignment->user_id === $user->id && $assignment->tenant_id === $user->tenant_id, 403, 'Anda tidak berhak mengubah assignment ini.');
        abort_unless($user->tenant && app(TenantEntitlement::class)->hasModule($user->tenant, $assignment->training_module_id), 403, 'Organisasi Anda belum mengaktifkan modul ini.');
        abort_unless(app(UserAccessManager::class)->hasModuleAccessById($user, $assignment->training_module_id), 403, 'Akses modul dibatasi oleh admin.');
        abort_if($assignment->status === 'cancelled' || ($assignment->status === 'completed' && ! $allowCompleted), 422, 'Assignment sudah berstatus terminal.');
    }

    private function ensureContentAvailable(ModuleAssignment $assignment): void
    {
        abort_if(in_array($assignment->status, ['completed', 'cancelled'], true), 422, 'Assignment sudah berstatus terminal.');

        $pretest = app(AssessmentLifecycle::class)->quiz($assignment, 'pretest');
        if ($pretest) {
            abort_unless(
                app(AssessmentLifecycle::class)->terminalAttempts($assignment, $pretest)->exists(),
                403,
                'Selesaikan baseline pretest terlebih dahulu.'
            );
        }
    }
}
