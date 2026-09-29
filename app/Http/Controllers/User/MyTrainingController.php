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

        $assignments = ModuleAssignment::with([
            'module:id,title,description,duration_minutes',
            'pretestQuiz:id,training_module_id,purpose',
            'posttestQuiz:id,training_module_id,purpose',
            'quizAttempts:id,module_assignment_id,quiz_id,status',
        ])
            ->where('user_id', $userId)
            ->whereIn('training_module_id', $entitledModuleIds)
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        // Filter out modules revoked from this user
        $manager = app(UserAccessManager::class);
        $lifecycle = app(AssessmentLifecycle::class);
        $assignments = $assignments
            ->filter(fn (ModuleAssignment $assignment): bool => $manager->hasModuleAccessById($request->user(), $assignment->training_module_id))
            ->map(function (ModuleAssignment $assignment) use ($lifecycle): array {
                $snapshot = $assignment->module_snapshot ?? $assignment->module->runtimeSnapshot();
                $state = $lifecycle->state($assignment);
                $bestPosttestScore = $this->bestValidPosttestScore($assignment, $state['posttest_quiz']?->id);

                return [
                    'id' => $assignment->id,
                    'training_module_id' => $assignment->training_module_id,
                    'status' => $assignment->status,
                    'stage' => $state['stage'],
                    'overdue' => $state['overdue'],
                    'deadline_at' => $assignment->deadline_at?->toIso8601String(),
                    'assigned_at' => $assignment->assigned_at?->toIso8601String(),
                    'best_posttest_score' => $bestPosttestScore,
                    'module' => [
                        'title' => $snapshot['title'],
                        'description' => $snapshot['description'],
                        'duration_minutes' => $snapshot['duration_minutes'],
                    ],
                ];
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

        $assignment->load(['module', 'pretestQuiz', 'posttestQuiz', 'quizAttempts:id,module_assignment_id,quiz_id,status']);
        $module = $assignment->module;
        $lifecycle = app(AssessmentLifecycle::class)->state($assignment);
        $pretestAttempt = $lifecycle['pretest_attempt'];
        $posttestAttempt = $lifecycle['posttest_attempt'];
        $pretestQuiz = $lifecycle['pretest_quiz'];
        $posttestQuiz = $lifecycle['posttest_quiz'];
        $bestPosttestScore = $this->bestValidPosttestScore($assignment, $posttestQuiz?->id);

        $snapshot = $assignment->module_snapshot ?? $module->runtimeSnapshot();
        $contentAvailable = $lifecycle['configuration_valid']
            && ($pretestQuiz === null || $pretestAttempt !== null);

        return Inertia::render('User/MyTraining/Show', [
            'assignment' => [
                'id' => $assignment->id,
                'status' => $assignment->status,
                'deadline_at' => $assignment->deadline_at?->toIso8601String(),
                'assigned_at' => $assignment->assigned_at?->toIso8601String(),
                'content_started_at' => $assignment->content_started_at?->toIso8601String(),
                'content_completed_at' => $assignment->content_completed_at?->toIso8601String(),
                'pretest_score' => $assignment->pretest_score,
                'best_posttest_score' => $bestPosttestScore,
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
            ] : null,
            'posttestQuiz' => $posttestQuiz ? [
                'id' => $posttestQuiz->id,
                'title' => $posttestQuiz->title,
                'passing_score' => $posttestQuiz->passing_score,
            ] : null,
            'pretestAttempt' => $pretestAttempt ? [
                'id' => $pretestAttempt->id,
                'score' => $pretestAttempt->score,
                'status' => $pretestAttempt->status,
                'submitted_at' => $pretestAttempt->submitted_at?->toIso8601String(),
            ] : null,
            'posttestAttempt' => $posttestAttempt ? [
                'id' => $posttestAttempt->id,
                'score' => $posttestAttempt->score,
                'status' => $posttestAttempt->status,
                'passed' => $posttestAttempt->passed,
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

        $lifecycle = app(AssessmentLifecycle::class);
        $state = $lifecycle->state($assignment);
        abort_unless($state['configuration_valid'], 422, 'Assessment sementara tidak tersedia.');

        $pretest = $lifecycle->quiz($assignment, 'pretest');
        if ($pretest) {
            abort_unless(
                $lifecycle->terminalAttempts($assignment, $pretest)->exists(),
                403,
                'Selesaikan baseline pretest terlebih dahulu.'
            );
        }
    }

    private function bestValidPosttestScore(ModuleAssignment $assignment, ?int $posttestQuizId): ?int
    {
        if ($posttestQuizId === null || ! $assignment->quizAttempts
            ->where('quiz_id', $posttestQuizId)
            ->where('status', 'submitted')
            ->isNotEmpty()) {
            return null;
        }

        return $assignment->score;
    }
}
