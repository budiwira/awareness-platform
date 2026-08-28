<?php

use App\Http\Controllers\Platform\TrainingModuleController as PlatformModuleController;
use App\Http\Controllers\Platform\TenantController as PlatformTenantController;
use App\Http\Controllers\Tenant\ModuleAssignmentController as TenantAssignmentController;
use App\Http\Controllers\User\MyTrainingController as UserTrainingController;
use App\Http\Controllers\Platform\QuizController as PlatformQuizController;
use App\Http\Controllers\User\ModuleQuizController as UserQuizController;
use App\Http\Controllers\User\MyScoreController as UserScoreController;
use App\Http\Controllers\Tenant\ReportController as TenantReportController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Platform\CaseStudyController as PlatformCaseController;
use App\Http\Controllers\User\CaseStudyController as UserCaseController;
use App\Http\Controllers\Platform\CtfChallengeController as PlatformCtfController;
use App\Http\Controllers\User\CtfController as UserCtfController;
use App\Http\Controllers\Platform\PlanController as PlatformPlanController;
use App\Http\Controllers\Platform\ReportController as PlatformReportController;
use App\Http\Controllers\Tenant\TtxController as TenantTtxController;
use App\Http\Controllers\Tenant\TtxExerciseController as TenantTtxExerciseController;
use App\Enums\UserRole;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Tenant\UserController as TenantUserController;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware('auth')->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.readAll');
    
    Route::get('/dashboard', function (Request $request) {
        \Log::info('DASHBOARD_CLOSURE', ['uid' => $request->user()?->id]);

        return match ($request->user()->role) {
            UserRole::SuperAdmin => redirect()->route('platform.dashboard'),
            UserRole::TenantAdmin => redirect()->route('tenant.dashboard'),
            UserRole::User => redirect()->route('user.dashboard'),
        };
    })->name('dashboard');
    
    Route::middleware('can:access-platform-dashboard')
        ->prefix('platform')
        ->name('platform.')
        ->group(function () {
            Route::get('/dashboard', function () {
                return Inertia::render('Platform/Dashboard', [
                    'stats' => [
                        'tenants' => Tenant::count(),
                        'active_tenants' => Tenant::where('status', 'active')->count(),
                        'users' => User::count(),
                        'tenant_admins' => User::where('role', UserRole::TenantAdmin->value)->count(),
                    ],
                    'tenants' => Tenant::withCount('users')
                        ->orderBy('name')
                        ->get()
                        ->map(fn (Tenant $t) => [
                            'name' => $t->name,
                            'slug' => $t->slug,
                            'status' => $t->status,
                            'users_count' => $t->users_count,
                        ]),
                ]);
            })->name('dashboard');

            // Route Tenants
            Route::get('/tenants', [PlatformTenantController::class, 'index'])->name('tenants.index');
            Route::post('/tenants', [PlatformTenantController::class, 'store'])->name('tenants.store');
            
            // Route Modules
            Route::get('/modules', [PlatformModuleController::class, 'index'])->name('modules.index');
            Route::post('/modules', [PlatformModuleController::class, 'store'])->name('modules.store');
            Route::patch('/modules/{module}', [PlatformModuleController::class, 'update'])->name('modules.update');
            Route::delete('/modules/{module}', [PlatformModuleController::class, 'destroy'])->name('modules.destroy');

            // Route Quizzes
            Route::get('/quizzes', [PlatformQuizController::class, 'index'])->name('quizzes.index');
            Route::post('/quizzes', [PlatformQuizController::class, 'store'])->name('quizzes.store');
            Route::get('/quizzes/{quiz}', [PlatformQuizController::class, 'show'])->name('quizzes.show');
            Route::post('/quizzes/{quiz}/questions', [PlatformQuizController::class, 'storeQuestion'])->name('quizzes.questions.store');

            // Route Cases
            Route::get('/cases', [PlatformCaseController::class, 'index'])->name('cases.index');
            Route::post('/cases', [PlatformCaseController::class, 'store'])->name('cases.store');
            Route::get('/cases/{caseStudy}', [PlatformCaseController::class, 'show'])->name('cases.show');
            Route::post('/cases/{caseStudy}/scenes', [PlatformCaseController::class, 'storeScene'])->name('cases.scenes.store');
            
            // Route CTF
            Route::get('/ctf', [PlatformCtfController::class, 'index'])->name('ctf.index');
            Route::post('/ctf', [PlatformCtfController::class, 'store'])->name('ctf.store');

            // Route Reports
            Route::get('/reports', [PlatformReportController::class, 'index'])->name('reports');

            // Route Plans
            Route::get('/plans', [PlatformPlanController::class, 'index'])->name('plans.index');
            Route::post('/plans', [PlatformPlanController::class, 'store'])->name('plans.store');
        });

    Route::middleware('can:access-tenant-dashboard')
        ->prefix('tenant')
        ->name('tenant.')
        ->group(function () {
            Route::get('/dashboard', function (Request $request) {
                $tenantId = $request->user()->tenant_id;

                return Inertia::render('Tenant/Dashboard', [
                    'stats' => [
                        'total_users' => User::where('tenant_id', $tenantId)->count(),
                        'active_users' => User::where('tenant_id', $tenantId)->where('is_active', true)->count(),
                        'admins' => User::where('tenant_id', $tenantId)->where('role', UserRole::TenantAdmin->value)->count(),
                    ],
                ]);
            })->name('dashboard');

            // Organization & User Management
            Route::get('/users', [TenantUserController::class, 'index'])->name('users.index');
            Route::post('/users', [TenantUserController::class, 'store'])->name('users.store');
            Route::patch('/users/{user}', [TenantUserController::class, 'update'])->name('users.update');
            Route::post('/users/import', [TenantUserController::class, 'import'])->name('users.import');
            
            // Route Assignments
            Route::get('/assignments', [TenantAssignmentController::class, 'index'])->name('assignments.index');
            Route::post('/assignments', [TenantAssignmentController::class, 'store'])->name('assignments.store');
            Route::patch('/assignments/{assignment}', [TenantAssignmentController::class, 'update'])->name('assignments.update');
            Route::get('/reports/export', [TenantReportController::class, 'export'])->name('reports.export');
            Route::get('/reports', [TenantReportController::class, 'index'])->name('reports');

            // Route TTX
            Route::get('/ttx', [TenantTtxController::class, 'index'])->name('ttx.index');
            Route::post('/ttx/playbooks', [TenantTtxController::class, 'storePlaybook'])->name('ttx.playbooks.store');
            Route::post('/ttx/runbooks', [TenantTtxController::class, 'storeRunbook'])->name('ttx.runbooks.store');
            
            // Route TTX Exercises & Teams
            Route::get('/ttx/exercises', [TenantTtxExerciseController::class, 'index'])->name('ttx.exercises.index');
            Route::post('/ttx/exercises', [TenantTtxExerciseController::class, 'store'])->name('ttx.exercises.store');
            Route::get('/ttx/exercises/{exercise}', [TenantTtxExerciseController::class, 'show'])->name('ttx.exercises.show');
            Route::post('/ttx/exercises/{exercise}/teams', [TenantTtxExerciseController::class, 'storeTeam'])->name('ttx.teams.store');
            Route::post('/ttx/teams/{team}/members', [TenantTtxExerciseController::class, 'storeTeamMember'])->name('ttx.teams.members.store');
            
            // Advanced TTX Exercise Routes
            Route::post('/ttx/exercises/{exercise}/advance', [TenantTtxExerciseController::class, 'advance'])->name('ttx.exercises.advance');
            Route::post('/ttx/exercises/{exercise}/injects', [TenantTtxExerciseController::class, 'storeInject'])->name('ttx.exercises.injects.store');
            Route::get('/ttx/exercises/{exercise}/evaluate', [TenantTtxExerciseController::class, 'evaluateForm'])->name('ttx.exercises.evaluate');
            Route::post('/ttx/exercises/{exercise}/evaluate', [TenantTtxExerciseController::class, 'evaluateStore'])->name('ttx.exercises.evaluate.store');
        });

    Route::middleware('can:access-user-dashboard')
        ->prefix('me')
        ->name('user.')
        ->group(function () {
            
            // Updated User Dashboard Closure
            Route::get('/dashboard', function (Request $request) {
                $user = $request->user();

                $assignments = \App\Models\ModuleAssignment::where('user_id', $user->id)->get();
                $attempts = \App\Models\QuizAttempt::where('user_id', $user->id)->get();
                $cases = \App\Models\CaseParticipation::where('user_id', $user->id)->get();
                $solves = \App\Models\CtfSolve::where('user_id', $user->id)->get();
                $ttx = \App\Models\TtxScore::where('user_id', $user->id)->get();
                $totalCtfPoints = (int) \App\Models\CtfChallenge::where('is_active', true)->sum('points');

                $score = (new \App\Support\Scoring\AwarenessScore)->compute(
                    $assignments, $attempts, $cases, $solves, $ttx, $totalCtfPoints
                );

                $pending = $assignments->where('status', 'assigned')->count();
                $inProgress = $assignments->where('status', 'in_progress')->count();

                return Inertia::render('User/Dashboard', [
                    'tenant_name' => $user->tenant?->name,
                    'score' => $score,
                    'pending' => $pending,
                    'in_progress' => $inProgress,
                ]);
            })->name('dashboard');

            // Route Training
            Route::get('/training', [UserTrainingController::class, 'index'])->name('training.index');
            Route::get('/training/{assignment}', [UserTrainingController::class, 'show'])->name('training.show');
            Route::patch('/training/{assignment}/complete', [UserTrainingController::class, 'markComplete'])->name('training.complete');

            // Route Quizzes for Users
            Route::get('/training/{assignment}/quiz', [UserQuizController::class, 'show'])->name('training.quiz');
            Route::post('/training/{assignment}/quiz', [UserQuizController::class, 'submit'])->name('training.quiz.submit');
            Route::get('/quiz-result/{attempt}', [UserQuizController::class, 'result'])->name('quiz.result');
            Route::get('/score', [UserScoreController::class, 'index'])->name('score');
            
            // Route Cases for Users
            Route::get('/cases', [UserCaseController::class, 'index'])->name('cases.index');
            Route::post('/cases/{caseStudy}/start', [UserCaseController::class, 'start'])->name('cases.start');
            Route::get('/cases/run/{participation}', [UserCaseController::class, 'run'])->name('cases.run');
            Route::post('/cases/run/{participation}', [UserCaseController::class, 'submit'])->name('cases.submit');
            Route::get('/cases/result/{participation}', [UserCaseController::class, 'result'])->name('cases.result');

            // Route CTF for Users
            Route::get('/ctf', [UserCtfController::class, 'index'])->name('ctf.index');
            Route::post('/ctf/{challenge}/submit', [UserCtfController::class, 'submit'])
                ->name('ctf.submit')
                ->middleware('throttle:10,1');
        });

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';