<?php

use App\Enums\UserRole;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PhishingTrapController;
use App\Http\Controllers\Platform\BillingRequestController as PlatformBillingRequestController;
use App\Http\Controllers\Platform\CaseStudyController as PlatformCaseController;
use App\Http\Controllers\Platform\CtfChallengeController as PlatformCtfController;
use App\Http\Controllers\Platform\PackageController as PlatformPackageController;
use App\Http\Controllers\Platform\QuizController as PlatformQuizController;
use App\Http\Controllers\Platform\TenantController as PlatformTenantController;
use App\Http\Controllers\Platform\TrainingModuleController as PlatformModuleController;
use App\Http\Controllers\Platform\UserAccessController as PlatformUserAccessController;
use App\Http\Controllers\Platform\UserController as PlatformUserController;
use App\Http\Controllers\PlatformReportController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Tenant\BillingController as TenantBillingController;
use App\Http\Controllers\Tenant\ModuleAssignmentController as TenantAssignmentController;
use App\Http\Controllers\Tenant\PhishingCampaignController as TenantPhishingController;
use App\Http\Controllers\Tenant\TtxController as TenantTtxController;
use App\Http\Controllers\Tenant\TtxExerciseController as TenantTtxExerciseController;
use App\Http\Controllers\Tenant\UserAccessController as TenantUserAccessController;
use App\Http\Controllers\Tenant\UserController as TenantUserController;
use App\Http\Controllers\TenantReportController;
use App\Http\Controllers\User\BadgeController;
use App\Http\Controllers\User\CaseStudyController as UserCaseController;
use App\Http\Controllers\User\CtfController as UserCtfController;
use App\Http\Controllers\User\LeaderboardController;
use App\Http\Controllers\User\ModuleQuizController as UserQuizController;
use App\Http\Controllers\User\MyScoreController as UserScoreController;
use App\Http\Controllers\User\MyTrainingController as UserTrainingController;
use App\Models\CaseParticipation;
use App\Models\CtfChallenge;
use App\Models\CtfSolve;
use App\Models\ModuleAssignment;
use App\Models\PhishingCampaign;
use App\Models\PhishingTarget;
use App\Models\QuizAttempt;
use App\Models\Tenant;
use App\Models\TtxScore;
use App\Models\User;
use App\Services\TenantEntitlement;
use App\Support\Scoring\AwarenessScore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Public/Landing');
})->name('landing');

// Public phishing trap
Route::get('/phish/{token}', [PhishingTrapController::class, 'show'])->name('phishing.trap');

Route::middleware('auth')->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.readAll');

    Route::get('/dashboard', function (Request $request) {
        Log::info('DASHBOARD_CLOSURE', ['uid' => $request->user()?->id]);

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
            Route::get('/dashboard', [PlatformReportController::class, 'index'])->name('dashboard');

            // Route Tenants
            Route::get('/tenants', [PlatformTenantController::class, 'index'])->name('tenants.index');
            Route::post('/tenants', [PlatformTenantController::class, 'store'])->name('tenants.store');
            Route::post('/tenants/set-package', [PlatformTenantController::class, 'setPackage'])->name('tenants.set-package');
            Route::get('/tenants/{tenant}/user-access', [PlatformUserAccessController::class, 'show'])->name('tenants.user-access.show');
            Route::post('/tenants/{tenant}/user-access', [PlatformUserAccessController::class, 'update'])->name('tenants.user-access.update');

            // Route Modules
            Route::get('/modules', [PlatformModuleController::class, 'index'])->name('modules.index');
            Route::get('/modules/create', [PlatformModuleController::class, 'create'])->name('modules.create');
            Route::post('/modules', [PlatformModuleController::class, 'store'])->name('modules.store');
            Route::get('/modules/{module}', [PlatformModuleController::class, 'show'])->name('modules.show');
            Route::get('/modules/{module}/edit', [PlatformModuleController::class, 'edit'])->name('modules.edit');
            Route::patch('/modules/{module}', [PlatformModuleController::class, 'update'])->name('modules.update');
            Route::post('/modules/{module}/publish', [PlatformModuleController::class, 'publish'])->name('modules.publish');
            Route::post('/modules/{module}/archive', [PlatformModuleController::class, 'archive'])->name('modules.archive');
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
            Route::post('/cases/{caseStudy}/publish', [PlatformCaseController::class, 'publish'])->name('cases.publish');
            Route::post('/cases/{caseStudy}/archive', [PlatformCaseController::class, 'archive'])->name('cases.archive');

            // Route CTF
            Route::get('/ctf', [PlatformCtfController::class, 'index'])->name('ctf.index');
            Route::post('/ctf', [PlatformCtfController::class, 'store'])->name('ctf.store');
            Route::post('/ctf/{challenge}/publish', [PlatformCtfController::class, 'publish'])->name('ctf.publish');
            Route::post('/ctf/{challenge}/archive', [PlatformCtfController::class, 'archive'])->name('ctf.archive');

            // Route Reports
            Route::get('/reports', [PlatformReportController::class, 'index'])->name('reports');

            // Route Packages
            Route::get('/packages', [PlatformPackageController::class, 'index'])->name('packages.index');
            Route::post('/packages', [PlatformPackageController::class, 'store'])->name('packages.store');
            Route::put('/packages/{package}', [PlatformPackageController::class, 'update'])->name('packages.update');

            // Route Billing Requests
            Route::get('/billing/requests', [PlatformBillingRequestController::class, 'index'])->name('billing.requests');
            Route::post('/billing/approve', [PlatformBillingRequestController::class, 'approve'])->name('billing.approve');
            Route::post('/billing/reject', [PlatformBillingRequestController::class, 'reject'])->name('billing.reject');

            // Route Users (cross-tenant)
            Route::get('/users', [PlatformUserController::class, 'index'])->name('users.index');
            Route::post('/users/destroy', [PlatformUserController::class, 'destroy'])->name('users.destroy');
        });

    Route::middleware('can:access-tenant-dashboard')
        ->prefix('tenant')
        ->name('tenant.')
        ->group(function () {
            Route::get('/dashboard', function (Request $request) {
                $tenantId = $request->user()->tenant_id;

                $users = User::where('tenant_id', $tenantId)->get();
                $assignments = ModuleAssignment::where('tenant_id', $tenantId)->get();
                $attempts = QuizAttempt::where('tenant_id', $tenantId)->get();
                $totalCtfPoints = (int) CtfChallenge::where('is_active', true)->sum('points');

                $gAssign = $assignments->groupBy('user_id');
                $gQuiz = $attempts->groupBy('user_id');
                $gCase = CaseParticipation::where('tenant_id', $tenantId)->get()->groupBy('user_id');
                $gSolve = CtfSolve::where('tenant_id', $tenantId)->get()->groupBy('user_id');
                $gTtx = TtxScore::where('tenant_id', $tenantId)->get()->groupBy('user_id');
                $gPhishing = PhishingTarget::whereHas('campaign', function ($q) use ($tenantId) {
                    $q->where('tenant_id', $tenantId);
                })->get()->groupBy('user_id');

                $scorer = new AwarenessScore;
                $entitlement = $request->user()->tenant ? app(TenantEntitlement::class)->getEntitledFeatures($request->user()->tenant) : null;

                // Hitung awareness score per user
                $scores = $users->map(function ($u) use ($scorer, $gAssign, $gQuiz, $gCase, $gSolve, $gTtx, $gPhishing, $totalCtfPoints, $entitlement) {
                    $awareness = $scorer->compute(
                        $gAssign->get($u->id, collect()),
                        $gQuiz->get($u->id, collect()),
                        $gCase->get($u->id, collect()),
                        $gSolve->get($u->id, collect()),
                        $gTtx->get($u->id, collect()),
                        $totalCtfPoints,
                        $entitlement,
                        $gPhishing->get($u->id, collect())
                    );

                    return $awareness['overall'];
                });

                $avgScore = $scores->count() > 0 ? (int) round($scores->avg()) : 0;

                // Distribusi 4 tier
                $baik = $scores->filter(fn ($s) => $s >= 70)->count();
                $cukup = $scores->filter(fn ($s) => $s >= 40 && $s < 70)->count();
                $perluPerbaikan = $scores->filter(fn ($s) => $s > 0 && $s < 40)->count();
                $belumMengerjakan = $scores->filter(fn ($s) => $s === 0)->count();

                // % penugasan completed
                $completionRate = $assignments->count() > 0
                    ? (int) round($assignments->where('status', 'completed')->count() / $assignments->count() * 100)
                    : 0;

                // Phishing awareness stats (jika tenant punya fitur phishing)
                $phishingStats = null;
                $hasPhishing = $entitlement && in_array('phishing', $entitlement, true);
                if ($hasPhishing) {
                    $allTargets = PhishingTarget::whereHas('campaign', function ($q) use ($tenantId) {
                        $q->where('tenant_id', $tenantId);
                    })->get();

                    $clickedCount = $allTargets->where('status', 'clicked')->count();
                    $sentCount = $allTargets->whereIn('status', ['sent', 'clicked'])->count();
                    $avgClickRate = $sentCount > 0 ? (int) round(($clickedCount / $sentCount) * 100) : 0;

                    $campaignsCount = PhishingCampaign::where('tenant_id', $tenantId)->count();

                    // Users at risk: click rate > 50%
                    $atRiskUsers = $gPhishing->filter(function ($targets) {
                        $clicked = $targets->where('status', 'clicked')->count();
                        $total = $targets->count();

                        return $total > 0 && ($clicked / $total) > 0.5;
                    })->count();

                    $phishingStats = [
                        'avg_click_rate' => $avgClickRate,
                        'campaigns_sent' => $campaignsCount,
                        'users_at_risk' => $atRiskUsers,
                    ];
                }

                return Inertia::render('Tenant/Dashboard', [
                    'stats' => [
                        'total_users' => $users->count(),
                        'active_users' => $users->where('is_active', true)->count(),
                        'admins' => $users->where('role', UserRole::TenantAdmin->value)->count(),
                        'avg_awareness_score' => $avgScore,
                        'completion_rate' => $completionRate,
                        'tier_baik' => $baik,
                        'tier_cukup' => $cukup,
                        'tier_perlu_perbaikan' => $perluPerbaikan,
                        'tier_belum_mengerjakan' => $belumMengerjakan,
                    ],
                    'phishingStats' => $phishingStats,
                ]);
            })->name('dashboard');

            // Organization & User Management
            Route::get('/users', [TenantUserController::class, 'index'])->name('users.index');
            Route::post('/users', [TenantUserController::class, 'store'])->name('users.store');
            Route::patch('/users/{user}', [TenantUserController::class, 'update'])->name('users.update');
            Route::post('/users/import', [TenantUserController::class, 'import'])->name('users.import');
            Route::get('/users/{user}/access', [TenantUserAccessController::class, 'show'])->name('users.access.show');
            Route::post('/users/{user}/access', [TenantUserAccessController::class, 'update'])->name('users.access.update');

            // Route Assignments
            Route::get('/assignments', [TenantAssignmentController::class, 'index'])->name('assignments.index');
            Route::post('/assignments', [TenantAssignmentController::class, 'store'])->name('assignments.store');
            Route::patch('/assignments/{assignment}', [TenantAssignmentController::class, 'update'])->name('assignments.update');
            Route::get('/reports/export', [TenantReportController::class, 'export'])->name('reports.export');
            Route::get('/reports/users/{user}', [TenantReportController::class, 'showUser'])->name('reports.users.show');
            Route::get('/reports', [TenantReportController::class, 'index'])->name('reports');

            // Route TTX
            Route::get('/ttx', [TenantTtxController::class, 'index'])->name('ttx.index');
            Route::get('/ttx/playbooks/{playbook}', [TenantTtxController::class, 'showPlaybook'])->name('ttx.playbooks.show');
            Route::get('/ttx/runbooks/{runbook}', [TenantTtxController::class, 'showRunbook'])->name('ttx.runbooks.show');
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

            // Route Billing
            Route::get('/billing', [TenantBillingController::class, 'index'])->name('billing.index');
            Route::post('/billing/subscribe', [TenantBillingController::class, 'subscribe'])->name('billing.subscribe');
            Route::post('/billing/request', [TenantBillingController::class, 'requestPackageChange'])->name('billing.request');

            // Route Phishing Campaigns
            Route::get('/phishing', [TenantPhishingController::class, 'index'])->name('phishing.index');
            Route::get('/phishing/create', [TenantPhishingController::class, 'create'])->name('phishing.create');
            Route::post('/phishing', [TenantPhishingController::class, 'store'])->name('phishing.store');
            Route::get('/phishing/{campaign}', [TenantPhishingController::class, 'show'])->name('phishing.show');
            Route::post('/phishing/{campaign}/send', [TenantPhishingController::class, 'send'])->name('phishing.send');
        });

    Route::middleware('can:access-user-dashboard')
        ->prefix('me')
        ->name('user.')
        ->group(function () {

            // Updated User Dashboard Closure
            Route::get('/dashboard', function (Request $request) {
                $user = $request->user();

                $assignments = ModuleAssignment::where('user_id', $user->id)->get();
                $attempts = QuizAttempt::where('user_id', $user->id)->get();
                $cases = CaseParticipation::where('user_id', $user->id)->get();
                $solves = CtfSolve::where('user_id', $user->id)->get();
                $ttx = TtxScore::where('user_id', $user->id)->get();
                $phishingTargets = PhishingTarget::where('user_id', $user->id)->get();
                $totalCtfPoints = (int) CtfChallenge::where('is_active', true)->sum('points');

                $score = (new AwarenessScore)->compute(
                    $assignments, $attempts, $cases, $solves, $ttx, $totalCtfPoints, null, $phishingTargets
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
            Route::post('/training/{assignment}/quiz/start', [UserQuizController::class, 'start'])->name('training.quiz.start');
            Route::get('/quiz/attempt/{attempt}', [UserQuizController::class, 'attempt'])->name('training.quiz.attempt');
            Route::post('/quiz/attempt/{attempt}/submit', [UserQuizController::class, 'submit'])->name('training.quiz.submit');
            Route::get('/quiz/attempt/{attempt}/review', [UserQuizController::class, 'review'])->name('training.quiz.review');
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

            // Route Badges & Leaderboard
            Route::get('/badges', [BadgeController::class, 'index'])->name('badges.index');
            Route::get('/leaderboard', [LeaderboardController::class, 'index'])->name('leaderboard.index');
        });

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/avatar', [ProfileController::class, 'uploadAvatar'])->name('profile.avatar');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});


require __DIR__.'/auth.php';
